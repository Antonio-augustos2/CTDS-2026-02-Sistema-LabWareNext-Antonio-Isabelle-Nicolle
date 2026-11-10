<?php
/**
 * Validação de currículos com a API Gemini.
 */

function getGeminiApiKey() {
    return defined('GEMINI_API_KEY') ? trim((string) GEMINI_API_KEY) : '';
}

/**
 * Remover a chave Gemini de qualquer texto que possa ser registrado.
 */
function redactGeminiApiKey($value) {
    $value = (string) $value;
    $apiKey = getGeminiApiKey();
    return $apiKey !== '' ? str_replace($apiKey, '[REDACTED]', $value) : $value;
}

/**
 * Registra erros de falha da API em um arquivo JSON
 */
function logApiError($errorType, $errorMessage, $fileName = null, $jobTitle = null, $dedupeMinutes = 0) {
    $logsFile = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'api_errors.json';
    $errorMessage = redactGeminiApiKey($errorMessage);
    $fileName = $fileName === null ? null : redactGeminiApiKey($fileName);
    $jobTitle = $jobTitle === null ? null : redactGeminiApiKey($jobTitle);

    $errors = [];
    if (file_exists($logsFile)) {
        $content = file_get_contents($logsFile);
        if ($content === false) {
            error_log('❌ Não foi possível ler o arquivo de erros da API Gemini.');
            return;
        }
        if ($content) {
            $decoded = json_decode($content, true);
            if (is_array($decoded) && isset($decoded['errors'])) {
                $errors = $decoded['errors'];
            }
        }
    }

    if ($dedupeMinutes > 0 && in_array($errorType, ['API_AUTH_ERROR', 'missing_api_key', 'invalid_api_key_format'], true) && !empty($errors)) {
        $latestError = end($errors);
        $latestTimestamp = is_array($latestError) ? strtotime((string) ($latestError['timestamp'] ?? '')) : false;
        if (
            is_array($latestError)
            && ($latestError['type'] ?? null) === $errorType
            && $latestTimestamp !== false
            && $latestTimestamp <= time()
            && time() - $latestTimestamp < $dedupeMinutes * 60
        ) {
            return;
        }
    }

    $errorRecord = [
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => $errorType,
        'message' => $errorMessage,
        'file_name' => $fileName,
        'job_title' => $jobTitle
    ];

    // Adicionar novo erro
    $errors[] = $errorRecord;
    
    // Manter apenas os últimos 100 erros
    if (count($errors) > 100) {
        $errors = array_slice($errors, -100);
    }
    
    // Salvar no arquivo
    $data = [
        'total_errors' => count($errors),
        'last_update' => date('Y-m-d H:i:s'),
        'errors' => $errors
    ];
    
    if (file_put_contents($logsFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
        error_log('❌ Não foi possível gravar o arquivo de erros da API Gemini.');
    }
}

function validateResumeFile($file) {
    $maxSize = 10 * 1024 * 1024;

    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'error' => 'Anexe um arquivo PDF ou DOCX válido.'];
    }

    if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxSize || !is_uploaded_file($file['tmp_name'])) {
        return ['valid' => false, 'error' => 'O arquivo deve ter até 10 MB e ser enviado pelo formulário.'];
    }

    $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($extension, ['pdf', 'docx'], true)) {
        return ['valid' => false, 'error' => 'Formato inválido. Envie somente arquivos .pdf ou .docx.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if ($extension === 'pdf') {
        $header = file_get_contents($file['tmp_name'], false, null, 0, 5);
        if ($mime !== 'application/pdf' || $header !== '%PDF-') {
            return ['valid' => false, 'error' => 'O arquivo não é um PDF válido.'];
        }
        return ['valid' => true, 'extension' => 'pdf', 'mime' => 'application/pdf'];
    }

    if ($mime !== 'application/zip' && $mime !== 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
        return ['valid' => false, 'error' => 'O arquivo não é um DOCX válido.'];
    }

    if (!class_exists('ZipArchive')) {
        return ['valid' => false, 'error' => 'O servidor não possui suporte para ler arquivos DOCX.'];
    }

    $zip = new ZipArchive();
    if ($zip->open($file['tmp_name']) !== true || $zip->locateName('word/document.xml') === false) {
        return ['valid' => false, 'error' => 'O arquivo não é um DOCX válido.'];
    }
    $zip->close();

    return ['valid' => true, 'extension' => 'docx', 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
}

function extractDocxText($path) {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return '';
    }

    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    if ($xml === false) {
        return '';
    }

    $xml = str_replace(['</w:p>', '</w:tr>', '<w:tab/>', '<w:br/>'], ["\n", "\n", "\t", "\n"], $xml);
    return trim(html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8'));
}

function callGemini($contents, $apiKey) {
    static $authenticationFailure = false;

    if ($authenticationFailure) {
        return [
            'success' => false,
            'error_code' => 'gemini_auth',
            'error' => 'A chave da API Gemini é inválida ou não tem permissão.'
        ];
    }

    if (trim((string) $apiKey) === '') {
        error_log('❌ GEMINI_API_KEY não está configurada.');
        logApiError('missing_api_key', 'GEMINI_API_KEY não está configurada.', null, null, 30);
        return [
            'success' => false,
            'error_code' => 'missing_api_key',
            'error' => 'A chave da API Gemini não está configurada.'
        ];
    }

    if (defined('GEMINI_API_KEY_FORMAT_OK') && !GEMINI_API_KEY_FORMAT_OK) {
        $error = 'GEMINI_API_KEY com formato não reconhecido. Use uma chave padrão AIza ou uma authorization key AQ. do Gemini.';
        logApiError('invalid_api_key_format', $error, null, null, 30);
        return [
            'success' => false,
            'error_code' => 'invalid_api_key_format',
            'error' => $error
        ];
    }

    // Erros transitórios permitem até 3 tentativas por modelo; falhas de autenticação encerram o fluxo.
    $payload = json_encode([
        'contents' => [$contents],
        'generationConfig' => [
            'temperature' => 0,
            'responseMimeType' => 'application/json'
        ]
    ], JSON_UNESCAPED_UNICODE);

    $modelos = ['gemini-3.6-flash', 'gemini-3.8-flash'];

    foreach ($modelos as $modeloIndex => $modelo) {
        $maxRetries = 3;
        $isFallback = $modeloIndex > 0;

        if ($isFallback) {
            error_log('⚠️ Fallback ativado após falha do modelo principal. O modelo alternativo terá até 3 tentativas para erros transitórios.');
        } else {
            error_log('▶️ Modelo principal definido: gemini-3.6-flash. O sistema fará até 3 tentativas para erros transitórios.');
        }

        for ($retentativa = 1; $retentativa <= $maxRetries; $retentativa++) {
            error_log("🔄 Tentando modelo $modelo (tentativa $retentativa/$maxRetries)" . ($isFallback ? ' [fallback]' : ' [principal]'));

            $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $modelo . ':generateContent';
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'x-goog-api-key: ' . $apiKey
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 120
            ]);

            $response = curl_exec($curl);
            $curlError = curl_error($curl);
            $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($response === false || $curlError !== '') {
                error_log("❌ Curl error: " . $curlError);
                if ($retentativa < $maxRetries) {
                    sleep(2 * $retentativa);
                    continue;
                }
                break;
            }

            if ($httpCode === 429 || $httpCode === 503) {
                $decoded = json_decode($response, true);
                $errorMsg = $decoded['error']['message'] ?? $response;
                $errorMsg = redactGeminiApiKey($errorMsg);
                error_log("⏳ HTTP $httpCode - Quota ou sobrecarga. Mensagem: " . substr($errorMsg, 0, 100));

                if ($retentativa < $maxRetries) {
                    $espera = pow(2, $retentativa) * 2;
                    error_log("⏳ Aguardando {$espera}s antes de retry...");
                    sleep($espera);
                    continue;
                }
                break;
            }

            if ($httpCode === 401 || $httpCode === 403) {
                $cause = stripos($response, 'ACCESS_TOKEN_TYPE_UNSUPPORTED') !== false
                    ? 'o Google sinalizou incompatibilidade no tipo de credencial; confirme a API key do AI Studio, o header x-goog-api-key e o endpoint v1beta'
                    : 'chave inexistente, revogada ou desabilitada (chaves expostas publicamente podem ser revogadas automaticamente pelo Google)';
                error_log("❌ Falha de autenticação Gemini (HTTP $httpCode): $cause.");
                logApiError('API_AUTH_ERROR', "HTTP $httpCode: $cause.", null, null, 30);
                $authenticationFailure = true;
                break 2;
            }

            if ($httpCode < 200 || $httpCode >= 300) {
                $decoded = json_decode($response, true);
                $errorMsg = $decoded['error']['message'] ?? $response;
                $errorMsg = redactGeminiApiKey($errorMsg);
                $logMessage = "❌ HTTP $httpCode: " . substr($errorMsg, 0, 150);
                error_log($logMessage);
                logApiError('API_HTTP_ERROR', "HTTP $httpCode: " . substr($errorMsg, 0, 200));

                if ($httpCode >= 400 && $httpCode < 500) {
                    break;
                }

                if ($retentativa < $maxRetries) {
                    $espera = pow(2, $retentativa) * 2;
                    error_log("⏳ Aguardando {$espera}s antes de retry...");
                    sleep($espera);
                    continue;
                }
                break;
            }

            $decoded = json_decode($response, true);
            if (!is_array($decoded)) {
                error_log("❌ Response não é JSON válido");
                logApiError('INVALID_JSON_RESPONSE', 'Resposta da API não é JSON válido', null, null);
                if ($retentativa < $maxRetries) {
                    sleep(2 * $retentativa);
                    continue;
                }
                break;
            }

            if (!isset($decoded['candidates']) || !is_array($decoded['candidates']) || count($decoded['candidates']) === 0) {
                error_log("❌ Resposta sem candidates: " . substr(redactGeminiApiKey(json_encode($decoded)), 0, 100));
                logApiError('MISSING_CANDIDATES', 'Resposta da API sem field candidates', null, null);
                if ($retentativa < $maxRetries) {
                    sleep(2 * $retentativa);
                    continue;
                }
                break;
            }

            $candidate = $decoded['candidates'][0];
            if (!isset($candidate['content']['parts'][0]['text'])) {
                error_log("❌ Resposta sem text na primeira parte");
                logApiError('MISSING_TEXT', 'Resposta da API sem text no campo esperado', null, null);
                if ($retentativa < $maxRetries) {
                    sleep(2 * $retentativa);
                    continue;
                }
                break;
            }

            $text = trim($candidate['content']['parts'][0]['text']);
            if (empty($text)) {
                error_log("❌ Resposta vazia do Gemini");
                logApiError('EMPTY_RESPONSE', 'Gemini retornou resposta vazia', null, null);
                if ($retentativa < $maxRetries) {
                    sleep(2 * $retentativa);
                    continue;
                }
                break;
            }

            $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text));
            $analysis = json_decode($text, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("❌ JSON inválido na resposta: " . json_last_error_msg() . " - " . substr(redactGeminiApiKey($text), 0, 100));
                logApiError('INVALID_JSON_PARSE', 'Erro ao decodificar JSON: ' . json_last_error_msg(), null, null);
                if ($retentativa < $maxRetries) {
                    sleep(2 * $retentativa);
                    continue;
                }
                break;
            }

            if (is_array($analysis)) {
                error_log("✓ Gemini API sucesso com modelo $modelo");
                return $analysis;
            }

            error_log("❌ JSON não retornou array: " . substr(redactGeminiApiKey($text), 0, 100));
            logApiError('JSON_NOT_ARRAY', 'JSON decodificado não é um array', null, null);
            if ($retentativa < $maxRetries) {
                sleep(2 * $retentativa);
                continue;
            }
            break;
        }

        error_log("❌ Todas as tentativas falharam com $modelo" . ($isFallback ? ' (fallback) ' : ' (principal)'));

        if ($modeloIndex < count($modelos) - 1) {
            error_log("⏭️ Falha total no modelo atual. Próximo passo: ativar fallback para o modelo alternativo.");
            sleep(2);
            continue;
        }
    }

    if ($authenticationFailure) {
        return [
            'success' => false,
            'error_code' => 'gemini_auth',
            'error' => 'A chave da API Gemini é inválida ou não tem permissão.'
        ];
    }

    error_log('⛔ Ambos os modelos falharam após todas as tentativas. A análise será encerrada sem gravar resultado no banco e a próxima execução do cron tentará novamente.');
    return null;
}

/**
 * Dicionário de sinônimos para melhorar matching de skills
 */
function buildSynonymMap() {
    return [
        'dev' => ['desenvolvedor', 'developer'],
        'desenvolvedor' => ['dev', 'developer', 'programador'],
        'backend' => ['back-end', 'backend developer'],
        'frontend' => ['front-end', 'frontend developer', 'ui', 'ux'],
        'fullstack' => ['full stack', 'fullstack developer'],
        'devops' => ['dev-ops', 'infrastructure'],
        'arquiteto' => ['architect', 'arquitetura'],
        'designer' => ['design', 'ux', 'ui', 'ui/ux'],
        'data' => ['data science', 'data analyst', 'analytics', 'big data'],
        'gerente' => ['manager', 'lead', 'líder', 'scrum master'],
        'análise' => ['analyst', 'analista', 'análise'],
        'teste' => ['test', 'qa', 'quality', 'tester'],
        'java' => ['java', 'spring', 'kotlin'],
        'python' => ['python', 'django', 'flask', 'fastapi'],
        'javascript' => ['js', 'node', 'nodejs', 'typescript'],
        'react' => ['react', 'vue', 'angular', 'frontend framework'],
        'database' => ['db', 'banco de dados', 'sql', 'nosql', 'mysql', 'postgres', 'mongodb'],
        'cloud' => ['aws', 'azure', 'gcp', 'google cloud'],
        'devops' => ['docker', 'kubernetes', 'ci/cd', 'jenkins', 'gitlab']
    ];
}

/**
 * Análise offline melhorada com reconhecimento de padrões e sinônimos
 */
function analyzeResumeOffline($file, $fileInfo, $jobDescription) {
    // Extrair texto do arquivo
    $text = '';
    $fileSizeBytes = 0;
    
    if ($fileInfo['extension'] === 'pdf') {
        $fileSizeBytes = filesize($file['tmp_name']);
        
        // PDF muito pequeno não é currículo
        if ($fileSizeBytes < 1000) {
            error_log("⚠ PDF muito pequeno: " . number_format($fileSizeBytes) . " bytes - rejeitado");
            return ['success' => true, 'is_curriculum' => false, 'reason' => 'Arquivo PDF muito pequeno'];
        }
        
        // PDFs válidos têm estrutura especial, não dá para extrair texto facilmente
        // Então confiamos no tamanho do arquivo como indicador
        $text = 'PDF'; // Texto mínimo para processamento
        
        error_log("✅ PDF válido detectado - Tamanho: " . number_format($fileSizeBytes) . " bytes");
    } else {
        $text = extractDocxText($file['tmp_name']);
        $fileSizeBytes = strlen($text);
        
        if (empty(trim($text))) {
            error_log("⚠ DOCX vazio ou sem conteúdo");
            return ['success' => true, 'is_curriculum' => false, 'reason' => 'Arquivo vazio ou sem conteúdo'];
        }
    }

    $textLower = strtolower($text);
    $textLength = strlen($text);

    // ===== MELHORADA: Seções de currículo em categorias de peso =====
    $curriculumStructure = [
        'HIGH_WEIGHT' => ['experiência profissional', 'trabalho', 'experiencia', 'projects', 'projetos'],
        'MEDIUM_WEIGHT' => ['formação acadêmica', 'educação', 'formacao', 'certificação', 'certificado', 'skills', 'habilidades', 'competências'],
        'LOW_WEIGHT' => ['idiomas', 'língua', 'linkedin', 'github', 'portfolio', 'contato', 'telefone']
    ];

    // Contar seções estruturais encontradas
    $structureScore = 0;
    foreach ($curriculumStructure['HIGH_WEIGHT'] as $keyword) {
        if (strpos($textLower, $keyword) !== false) $structureScore += 3;
    }
    foreach ($curriculumStructure['MEDIUM_WEIGHT'] as $keyword) {
        if (strpos($textLower, $keyword) !== false) $structureScore += 2;
    }
    foreach ($curriculumStructure['LOW_WEIGHT'] as $keyword) {
        if (strpos($textLower, $keyword) !== false) $structureScore += 1;
    }

    // Detectar padrões de datas (2020-2023, jan/2020, etc)
    $hasDatePatterns = (bool) preg_match('/(\d{4}|\d{1,2}\/\d{2}|\d{1,2}\/\d{4}|jan|fev|mar|abr|mai|jun|jul|ago|set|out|nov|dez)/i', $text);
    if ($hasDatePatterns) $structureScore += 2;

    // Detectar seção de contato (email, telefone)
    $hasContact = (bool) preg_match('/@|[\d]{2,3}[-\s][\d]{4,5}[-\s][\d]{4}/', $text);
    if ($hasContact) $structureScore += 2;

    // ===== CRÍTICO: Para PDFs, usar tamanho do arquivo como indicador =====
    // PDFs maiores que 5KB geralmente são currículos legítimos
    if ($fileInfo['extension'] === 'pdf') {
        if ($fileSizeBytes > 5000) {
            // PDF grande é provavelmente currículo
            $structureScore = max($structureScore, 4);
            error_log("✅ PDF grande (" . number_format($fileSizeBytes) . " bytes) aceito como currículo");
        } elseif ($fileSizeBytes > 2000 && $structureScore == 0) {
            // PDF médio com nenhuma keyword é borderline - aceitar por tamanho
            $structureScore = 2;
            error_log("✅ PDF médio (" . number_format($fileSizeBytes) . " bytes) aceito por tamanho");
        }
    }

    // Decisão: é currículo?
    // Requer estrutura clara (score >= 3) OU arquivo grande
    $isCurriculum = false;
    
    if ($structureScore >= 3) {
        $isCurriculum = true;
        error_log("✅ Currículo aceito - Score estrutural: $structureScore");
    } elseif ($fileInfo['extension'] === 'docx' && $textLength > 1000 && $structureScore >= 2) {
        $isCurriculum = true;
        error_log("✅ DOCX aceito - Score: $structureScore, Tamanho: " . number_format($textLength));
    } elseif ($fileInfo['extension'] === 'pdf' && $fileSizeBytes > 3000) {
        $isCurriculum = true;
        error_log("✅ PDF aceito por tamanho - " . number_format($fileSizeBytes) . " bytes");
    }

    if (!$isCurriculum) {
        error_log("❌ Currículo REJEITADO - Score: $structureScore, Tamanho: " . number_format($fileSizeBytes) . " bytes, Tipo: {$fileInfo['extension']}, Contato: " . ($hasContact ? 'sim' : 'não'));
        return ['success' => true, 'is_curriculum' => false, 'reason' => 'Arquivo não apresenta estrutura de currículo (score: ' . $structureScore . ')'];
    }

    error_log("ℹ Análise offline: Currículo aceito - Score: $structureScore, Tamanho: " . number_format($fileSizeBytes) . " bytes");

    // ===== MELHORADA: Análise de Assertividade com Sinônimos e Contexto =====
    
    // Extrair palavras-chave da vaga (técnicas e funcionais)
    $vagaSkills = extractSkillsFromText($jobDescription);
    
    if (empty($vagaSkills)) {
        return [
            'success' => true,
            'is_curriculum' => true,
            'assertividade' => 50,
            'feedback' => 'Currículo válido detectado, mas não foi possível extrair skills da vaga para comparação detalhada. (✓ Análise offline)'
        ];
    }

    // Encontrar skills no currículo com análise contextual
    $synonymMap = buildSynonymMap();
    $foundSkills = [];
    $skillWeights = [];

    foreach ($vagaSkills as $skill) {
        $skill_lower = strtolower($skill);
        
        // Verificar skill direto
        $found = stripos($textLower, $skill_lower) !== false;
        
        // Verificar sinônimos se disponível
        if (!$found && isset($synonymMap[$skill_lower])) {
            foreach ($synonymMap[$skill_lower] as $synonym) {
                if (stripos($textLower, $synonym) !== false) {
                    $found = true;
                    break;
                }
            }
        }
        
        if ($found) {
            $foundSkills[] = $skill;
            
            // Dar peso extra se encontrado perto de "experiência" ou "projeto"
            if (preg_match("/experiência.*?" . preg_quote($skill_lower, '/') . "|" . preg_quote($skill_lower, '/') . ".*?projeto/i", $text)) {
                $skillWeights[$skill] = 1.5;
            } elseif (preg_match("/projeto.*?" . preg_quote($skill_lower, '/') . "/i", $text)) {
                $skillWeights[$skill] = 1.3;
            } else {
                $skillWeights[$skill] = 1.0;
            }
        }
    }

    // Calcular assertividade ponderada
    $totalWeight = 0;
    foreach ($foundSkills as $skill) {
        $totalWeight += ($skillWeights[$skill] ?? 1.0);
    }
    
    $maxPossibleWeight = count($vagaSkills) * 1.5; // Score máximo teórico
    $assertividade = (int) round(($totalWeight / $maxPossibleWeight) * 100);
    $assertividade = max(25, min(100, $assertividade)); // Piso 25%, teto 100%

    // Gerar feedback específico baseado em skills encontradas
    $feedback = generateDetailedFeedback($assertividade, $foundSkills, $vagaSkills, count($foundSkills));

    return [
        'success' => true,
        'is_curriculum' => true,
        'assertividade' => $assertividade,
        'feedback' => $feedback . ' (✓ Análise offline)'
    ];
}

/**
 * Extrair skills/competências de um texto
 */
function extractSkillsFromText($text) {
    // Expandir lista de skills procurados
    $technicalSkills = [
        'java', 'python', 'javascript', 'typescript', 'php', 'c#', 'golang', 'rust', 'kotlin',
        'react', 'vue', 'angular', 'node', 'express', 'django', 'flask', 'spring',
        'sql', 'mysql', 'postgresql', 'mongodb', 'redis', 'elasticsearch',
        'aws', 'azure', 'gcp', 'docker', 'kubernetes', 'ci/cd',
        'git', 'linux', 'windows', 'html', 'css', 'rest api', 'graphql',
        'devops', 'backend', 'frontend', 'fullstack', 'mobile', 'android', 'ios',
        'api', 'microservices', 'agile', 'scrum', 'kanban',
        'data', 'analytics', 'big data', 'machine learning', 'ai', 'deep learning'
    ];
    
    $functionalSkills = [
        'gerenciamento', 'liderança', 'comunicação', 'resolução de problemas',
        'análise', 'planejamento', 'organização', 'documentação',
        'qualidade', 'testes', 'requisitos', 'arquitetura',
        'performance', 'segurança', 'escalabilidade'
    ];

    $allSkills = array_merge($technicalSkills, $functionalSkills);
    $foundSkills = [];
    $textLower = strtolower($text);

    foreach ($allSkills as $skill) {
        // Usar word boundaries para matching mais preciso
        if (preg_match('/\b' . preg_quote($skill, '/') . '\b/i', $text)) {
            $foundSkills[] = $skill;
        }
    }

    return array_unique($foundSkills);
}

/**
 * Gerar feedback detalhado baseado na análise
 */
function generateDetailedFeedback($assertividade, $foundSkills, $allSkills, $foundCount) {
    $totalSkills = count($allSkills);
    $missingSkills = array_diff($allSkills, $foundSkills);
    $missingCount = count($missingSkills);

    $matchPercentage = ($totalSkills > 0) ? round(($foundCount / $totalSkills) * 100) : 0;

    // Feedback baseado em pontuação
    if ($assertividade >= 85) {
        $baseFeedback = "🌟 EXCELENTE MATCH! Seu currículo possui excelente aderência aos requisitos da vaga. $foundCount/$totalSkills competências foram encontradas.";
        if ($missingCount > 0 && $missingCount <= 3) {
            $missing = implode(', ', array_slice(array_values($missingSkills), 0, 3));
            $baseFeedback .= " Áreas para desenvolvimento: $missing.";
        }
    } elseif ($assertividade >= 70) {
        $baseFeedback = "✅ BOM MATCH! Seu currículo atende bem os requisitos. Encontradas $foundCount/$totalSkills competências principais.";
        if ($missingCount > 0 && $missingCount <= 4) {
            $missing = implode(', ', array_slice(array_values($missingSkills), 0, 4));
            $baseFeedback .= " Você poderia fortalecer: $missing.";
        }
    } elseif ($assertividade >= 50) {
        $baseFeedback = "⚠️ MATCH PARCIAL. Seu currículo apresenta algumas competências relevantes ($foundCount/$totalSkills encontradas).";
        $missing = implode(', ', array_slice(array_values($missingSkills), 0, 5));
        $baseFeedback .= " Considere desenvolver conhecimento em: $missing.";
    } else {
        $baseFeedback = "ℹ️ DIFERENTES TRAJECTOS. Seu currículo tem apenas $foundCount competências da vaga ($missingCount ausentes).";
        $baseFeedback .= " Pode ser uma oportunidade de requalificação ou crescimento profissional.";
    }

    return $baseFeedback;
}

function analyzeResumeWithGemini($file, $fileInfo, $jobDescription) {
    $apiKey = getGeminiApiKey();
    if ($apiKey === '') {
        error_log('❌ GEMINI_API_KEY não está configurada.');
        logApiError('missing_api_key', 'GEMINI_API_KEY não está configurada.', null, null, 30);
        return ['success' => false, 'error_code' => 'missing_api_key', 'error' => 'A análise de currículos não está configurada no servidor.'];
    }

    if (defined('GEMINI_API_KEY_FORMAT_OK') && !GEMINI_API_KEY_FORMAT_OK) {
        $error = 'GEMINI_API_KEY com formato não reconhecido. Use uma chave padrão AIza ou uma authorization key AQ. do Gemini.';
        logApiError('invalid_api_key_format', $error, null, null, 30);
        return ['success' => false, 'error_code' => 'invalid_api_key_format', 'error' => $error];
    }

    $prompt = 'Extraia o conteúdo do arquivo enviado e analise se ele está no formato de um currículo profissional. ' .
        'Considere nome, contato, experiências, formação, competências e seções equivalentes. ' .
        'Responda SOMENTE com JSON válido, sem markdown, neste formato: ' .
        '{"is_curriculum":true,"reason":"motivo curto em português"}. ' .
        'Marque false para formulários, cartas, documentos vazios, textos genéricos ou documentos que não sejam currículos.';

    $filePart = null;
    if ($fileInfo['extension'] === 'pdf') {
        $filePart = ['inline_data' => [
            'mime_type' => 'application/pdf',
            'data' => base64_encode(file_get_contents($file['tmp_name']))
        ]];
    } else {
        $text = extractDocxText($file['tmp_name']);
        if ($text === '') {
            return ['success' => false, 'error' => 'Não foi possível extrair texto do DOCX para análise.'];
        }
        $filePart = ['text' => "Conteúdo extraído do arquivo:\n" . mb_substr($text, 0, 50000)];
    }

    $firstAnalysis = callGemini([
        'parts' => [['text' => $prompt], $filePart]
    ], $apiKey);

    if (is_array($firstAnalysis) && ($firstAnalysis['success'] ?? null) === false && isset($firstAnalysis['error_code'])) {
        return $firstAnalysis;
    }

    if ($firstAnalysis === null) {
        error_log('⚠ Gemini API falhou na validação do currículo.');
        logApiError('VALIDATION_FAILED', 'API Gemini falhou na validação do currículo', $file['name'] ?? 'desconhecido');
        return ['success' => false, 'error' => 'Não foi possível validar o arquivo no momento.'];
    }

    if (!is_array($firstAnalysis) || !array_key_exists('is_curriculum', $firstAnalysis)) {
        error_log('Gemini retornou uma resposta inválida na validação do currículo: ' . redactGeminiApiKey(json_encode($firstAnalysis)));
        return ['success' => false, 'error' => 'Não foi possível interpretar a análise do arquivo.'];
    }

    if (!filter_var($firstAnalysis['is_curriculum'], FILTER_VALIDATE_BOOLEAN)) {
        return [
            'success' => true,
            'is_curriculum' => false,
            'reason' => trim((string) ($firstAnalysis['reason'] ?? ''))
        ];
    }

    $secondPrompt = 'Analise os requisitos da vaga à qual o usuário se candidatou. ' .
        'Compare esses requisitos com o conteúdo do currículo enviado e dê uma porcentagem de assertividade ' .
        'do currículo para a vaga e um feedback do currículo para a vaga. ' .
        'Responda SOMENTE com JSON válido, sem markdown, neste formato: ' .
        '{"assertividade":0.0,"feedback":"feedback detalhado em português"}. ' .
        'A assertividade deve ser um número entre 0 e 100, sem o símbolo de porcentagem. ' .
        "Requisitos e descrição da vaga:\n" . mb_substr((string) $jobDescription, 0, 20000);

    $secondAnalysis = callGemini([
        'parts' => [['text' => $secondPrompt], $filePart]
    ], $apiKey);

    if (is_array($secondAnalysis) && ($secondAnalysis['success'] ?? null) === false && isset($secondAnalysis['error_code'])) {
        return $secondAnalysis;
    }

    if ($secondAnalysis === null) {
        error_log('⚠ Gemini API falhou na análise de assertividade (callGemini retornou null).');
        logApiError('ASSERTIVITY_FAILED', 'API Gemini falhou na análise de assertividade', $file['name'] ?? 'desconhecido');
        return ['success' => false, 'error' => 'Não foi possível analisar o currículo no momento.'];
    }

    $score = $secondAnalysis['assertividade'] ?? null;
    if (!is_array($secondAnalysis) || !is_numeric($score) || !array_key_exists('feedback', $secondAnalysis)) {
        $responseSummary = redactGeminiApiKey(json_encode($secondAnalysis));
        error_log('Gemini retornou uma resposta inválida na comparação com a vaga: ' . $responseSummary);
        logApiError('INVALID_RESPONSE', 'Resposta inválida do Gemini: ' . $responseSummary, $file['name'] ?? 'desconhecido');
        return ['success' => false, 'error' => 'Não foi possível interpretar a análise da vaga.'];
    }

    $score = max(0, min(100, (float) $score));

    return [
        'success' => true,
        'is_curriculum' => true,
        'assertividade' => $score,
        'feedback' => trim((string) $secondAnalysis['feedback'])
    ];
}

/**
 * Analisar currículo com Gemini API (executado via CRON de forma assíncrona).
 * 
 * Esta função é chamada pelo cron_analisar.php para processar candidaturas
 * pendentes sem bloquear o fluxo de submissão do formulário.
 * 
 * @param $conn - Conexão com banco de dados
 * @param $caminhoArquivo - Caminho do arquivo (relativo ou absoluto)
 * @param $descricaoVaga - Descrição detalhada da vaga
 * @param $idCandidatura - ID da candidatura sendo processada
 * 
 * @return array - Array com chaves:
 *   - 'success' (bool) - Se análise foi realizada com sucesso
 *   - 'erro' (string) - Mensagem de erro se não sucesso
 *   - 'assertividade' (float) - Percentual de assertividade (0-100)
 *   - 'feedback' (string) - Feedback da análise
 */
function analizarComApi($conn, $caminhoArquivo, $descricaoVaga, $idCandidatura) {
    require_once 'db_functions.php';
    
    $idCandidatura = (int)$idCandidatura;
    
    error_log("📋 [CRON] Iniciando análise da candidatura #$idCandidatura");
    
    // 1️⃣ Validar se arquivo existe
    if (empty($caminhoArquivo) || !file_exists($caminhoArquivo)) {
        $mensagemErro = "Arquivo não encontrado: $caminhoArquivo";
        error_log("❌ [CRON] $mensagemErro");
        return ['success' => false, 'erro' => $mensagemErro];
    }
    
    // 2️⃣ Validar extensão do arquivo
    $extensao = strtolower(pathinfo($caminhoArquivo, PATHINFO_EXTENSION));
    if (!in_array($extensao, ['pdf', 'docx'], true)) {
        $mensagemErro = "Formato de arquivo não suportado: $extensao";
        error_log("❌ [CRON] $mensagemErro");
        return ['success' => false, 'erro' => $mensagemErro];
    }
    
    // 3️⃣ Preparar dados do arquivo
    $file = [
        'tmp_name' => $caminhoArquivo,
        'name' => basename($caminhoArquivo),
        'size' => filesize($caminhoArquivo)
    ];
    
    $fileInfo = ['extension' => $extensao];
    if ($extensao === 'pdf') {
        $fileInfo['mime'] = 'application/pdf';
    } else {
        $fileInfo['mime'] = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }
    
    // 4️⃣ Aumentar tempo de execução para requisição à API
    $timeoutAnterior = ini_get('max_execution_time');
    set_time_limit(120);
    
    // 5️⃣ Executar análise com Gemini
    error_log("🤖 [CRON] Enviando para análise com Gemini...");
    $analise = analyzeResumeWithGemini($file, $fileInfo, $descricaoVaga);
    
    // Restaurar timeout anterior
    set_time_limit((int)$timeoutAnterior ?: 30);
    
    // 6️⃣ Validar resultado da análise
    if (!$analise || !$analise['success']) {
        $mensagemErro = $analise['error'] ?? 'Erro desconhecido na análise';
        error_log("❌ [CRON] Análise com Gemini falhou: $mensagemErro");
        return ['success' => false, 'erro' => $mensagemErro];
    }
    
    // 7️⃣ Verificar se é realmente um currículo
    if (!$analise['is_curriculum']) {
        $motivo = $analise['reason'] ?? 'Arquivo não parece ser um currículo válido';
        error_log("⚠️  [CRON] Arquivo rejeitado como currículo: $motivo");
        return ['success' => false, 'erro' => $motivo];
    }
    
    // 8️⃣ Extrair assertividade e feedback
    $assertividade = $analise['assertividade'] ?? 0;
    $feedback = $analise['feedback'] ?? '';
    
    // 9️⃣ Salvar resultado no banco de dados
    $atualizouAnalise = updateCandidaturaAnalise($conn, $idCandidatura, $assertividade, $feedback);
    
    if (!$atualizouAnalise) {
        error_log("❌ [CRON] Falha ao gravar resultado da análise no banco para candidatura #$idCandidatura");
        return ['success' => false, 'erro' => 'Falha ao gravar resultado no banco de dados'];
    }
    
    // 🔟 Marcar análise como completa
    $statusAtualizado = updateCandidaturaStatus($conn, $idCandidatura, 'Análise Completa');
    
    if ($statusAtualizado) {
        error_log("✅ [CRON] Candidatura #$idCandidatura analisada com sucesso! Assertividade: " . number_format($assertividade, 2) . "%");
        return [
            'success' => true,
            'assertividade' => $assertividade,
            'feedback' => $feedback
        ];
    } else {
        error_log("⚠️  [CRON] Análise concluída mas falha ao atualizar status para candidatura #$idCandidatura");
        return [
            'success' => true,
            'assertividade' => $assertividade,
            'feedback' => $feedback,
            'aviso' => 'Análise concluída mas status não foi atualizado'
        ];
    }
}