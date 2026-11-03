-- Dump sanitizado: cont?m apenas estrutura e dados fict?cios. Nunca versionar dados reais de candidatos.

SET FOREIGN_KEY_CHECKS=0;
SET UNIQUE_CHECKS=0;
DROP DATABASE IF EXISTS labw;
CREATE DATABASE labw;
USE labw;

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geraÃ§Ã£o: 15/09/2026 Ã s 15:57
-- VersÃ£o do servidor: 10.4.32-MariaDB
-- VersÃ£o do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `labw`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `acesso`
--

DROP TABLE IF EXISTS `acesso`; CREATE TABLE `acesso` (
  `id_acesso` int(11) NOT NULL,
  `id_vaga` int(11) NOT NULL,
  `titulo_vaga` varchar(150) DEFAULT NULL,
  `visitor_id` char(64) DEFAULT NULL,
  `data_acesso` timestamp NOT NULL DEFAULT current_timestamp(),
  `tipo_acesso` varchar(30) NOT NULL DEFAULT 'pagina_vaga'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- --------------------------------------------------------

--
-- Estrutura para tabela `candidato`
--

DROP TABLE IF EXISTS `candidato`; CREATE TABLE `candidato` (
  `id_candidato` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `curriculo` longblob DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Pendente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--

-- --------------------------------------------------------

--
-- Estrutura para tabela `candidatura`
--

DROP TABLE IF EXISTS `candidatura`; CREATE TABLE `candidatura` (
  `id_candidatura` int(11) NOT NULL,
  `id_candidato` int(11) NOT NULL,
  `id_vaga` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  `assertividade` decimal(5,2) DEFAULT NULL,
  `feedback` longtext DEFAULT NULL,
  `arquivo_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- --------------------------------------------------------

--
-- Estrutura para tabela `empresa`
--

DROP TABLE IF EXISTS `empresa`; CREATE TABLE `empresa` (
  `id_empresa` int(11) NOT NULL,
  `razao_social` varchar(150) NOT NULL,
  `cnpj` varchar(18) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `empresa`
--

INSERT INTO `empresa` (`id_empresa`, `razao_social`, `cnpj`) VALUES
(1, 'LabWare Brasil', '12.345.678/0001-90');

-- --------------------------------------------------------

--
-- Estrutura para tabela `entrevista`
--

DROP TABLE IF EXISTS `entrevista`; CREATE TABLE `entrevista` (
  `id_entrevista` int(11) NOT NULL,
  `id_candidatura` int(11) NOT NULL,
  `id_rh` int(11) NOT NULL,
  `data_hora` datetime NOT NULL,
  `resultado` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `feedback`
--

DROP TABLE IF EXISTS `feedback`; CREATE TABLE `feedback` (
  `id_feedback` int(11) NOT NULL,
  `autor` varchar(150) NOT NULL,
  `cargo` varchar(150) NOT NULL,
  `mensagem` text NOT NULL,
  `id_rh_autor` int(11) DEFAULT NULL,
  `destaque` tinyint(1) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- --------------------------------------------------------

--
-- Estrutura para tabela `rh`
--

DROP TABLE IF EXISTS `rh`; CREATE TABLE `rh` (
  `id_rh` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `cargo` varchar(150) NOT NULL DEFAULT 'RH',
  `login_email` varchar(255) DEFAULT NULL,
  `id_empresa` int(11) NOT NULL,
  `senha` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--INSERT INTO `rh` (`id_rh`, `nome`, `cargo`, `login_email`, `id_empresa`, `senha`) VALUES
(1, 'Admin LabWare', 'RH', 'admin@exemplo.com', 1, '$2y$10$hashficticio');

-- --------------------------------------------------------

--
-- Estrutura para tabela `vaga`
--

DROP TABLE IF EXISTS `vaga`; CREATE TABLE `vaga` (
  `id_vaga` int(11) NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `descricao` text DEFAULT NULL,
  `status` varchar(50) NOT NULL,
  `id_empresa` int(11) NOT NULL,
  `id_rh_responsavel` int(11) NOT NULL,
  `tipo` varchar(100) NOT NULL DEFAULT 'Desenvolvedor â€¢ Remoto',
  `localizacao` varchar(150) NOT NULL DEFAULT 'Remoto',
  `descricao_vaga` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `vaga`
--

INSERT INTO `vaga` (`id_vaga`, `titulo`, `descricao`, `status`, `id_empresa`, `id_rh_responsavel`, `tipo`, `localizacao`, `descricao_vaga`) VALUES
(1, 'Desenvolvedor Full Stack', '**DescriÃ§Ã£o do trabalho**\n\nBuscamos um Desenvolvedor Full Stack Pleno para atuar no desenvolvimento de aplicaÃ§Ãµes web, participando tanto da construÃ§Ã£o do front-end quanto do back-end e contribuindo para a criaÃ§Ã£o de soluÃ§Ãµes eficientes e escalÃ¡veis.\n\n**Responsabilidades**\n\nDesenvolver e manter aplicaÃ§Ãµes front-end e back-end, criar e integrar APIs REST, trabalhar com bancos de dados relacionais, realizar manutenÃ§Ã£o e correÃ§Ã£o de problemas e colaborar com a equipe de desenvolvimento.\n\n**Requisitos DesejÃ¡veis**\n\nExperiÃªncia com JavaScript/TypeScript, HTML, CSS, React, Angular ou Vue.js. Conhecimento em APIs REST, bancos de dados relacionais, Git e boas prÃ¡ticas de programaÃ§Ã£o. Perfil organizado, proativo e com facilidade para trabalhar em equipe.\n\n**RemuneraÃ§Ã£o e BenefÃ­cios**\n\nSalÃ¡rio compatÃ­vel com o mercado e benefÃ­cios definidos de acordo com a polÃ­tica da empresa.\n\n**InformaÃ§Ãµes Adicionais**\n\nVaga para profissional de nÃ­vel pleno, com atuaÃ§Ã£o colaborativa e foco na resoluÃ§Ã£o de problemas. ExperiÃªncia com Docker, cloud, testes automatizados e metodologias Ã¡geis serÃ¡ considerada um diferencial.', 'Aberta', 1, 1, 'Pleno â€¢ Remoto', 'Remoto', NULL),
(3, 'dev front end', '**DescriÃ§Ã£o do trabalho**\n\nBuscamos um Desenvolvedor Front-end SÃªnior para atuar no desenvolvimento de aplicaÃ§Ãµes web modernas, escalÃ¡veis e de alta performance, utilizando tecnologias como React e Next.js.\n\n**Responsabilidades**\n\nDesenvolver e manter aplicaÃ§Ãµes front-end, implementar interfaces responsivas e acessÃ­veis, trabalhar com APIs, realizar Code Review e testes automatizados, alÃ©m de contribuir para decisÃµes tÃ©cnicas e arquitetura das aplicaÃ§Ãµes.\n\n**Requisitos DesejÃ¡veis**\n\nExperiÃªncia sÃ³lida com HTML, CSS, JavaScript, TypeScript, React e Next.js. Conhecimento em Git, Clean Code, arquitetura de aplicaÃ§Ãµes, performance, responsividade, acessibilidade e testes automatizados.\n\n**RemuneraÃ§Ã£o e BenefÃ­cios**\n\nSalÃ¡rio compatÃ­vel com o mercado e benefÃ­cios a serem definidos de acordo com a polÃ­tica da empresa.\n\n**InformaÃ§Ãµes Adicionais**\n\nVaga para atuaÃ§Ã£o remota, com necessidade de autonomia e capacidade de tomada de decisÃµes tÃ©cnicas. Conhecimentos em Docker, CI/CD, cloud, Design Systems e experiÃªncia em lideranÃ§a tÃ©cnica serÃ£o considerados diferenciais.', 'Aberta', 1, 1, 'senior â€¢ Remoto', 'Remoto', NULL),
(4, 'Analista de dados - pleno', '**DescriÃ§Ã£o do trabalho**\n\nBuscamos um Analista de Dados Pleno para atuar na coleta, tratamento e anÃ¡lise de dados, transformando informaÃ§Ãµes em insights que apoiem as decisÃµes estratÃ©gicas da empresa.\n\n**Responsabilidades**\n\nCriar dashboards, indicadores e relatÃ³rios, realizar anÃ¡lises de dados e transformar informaÃ§Ãµes em insights para apoiar a tomada de decisÃµes da empresa.\n\n**Requisitos DesejÃ¡veis**\n\nConhecimento em SQL, Power BI, Excel e Python. Perfil analÃ­tico, organizaÃ§Ã£o, boa comunicaÃ§Ã£o e capacidade de trabalhar de forma autÃ´noma e colaborativa.\n\n**RemuneraÃ§Ã£o e BenefÃ­cios**\n\nSalÃ¡rio compatÃ­vel com o mercado e benefÃ­cios a serem definidos de acordo com a polÃ­tica da empresa.\n\n**InformaÃ§Ãµes Adicionais**\n\nVaga para profissional de nÃ­vel pleno, com atuaÃ§Ã£o integrada Ã s demais equipes da empresa e possibilidade de trabalho autÃ´nomo e colaborativo.', 'Aberta', 1, 1, 'pleno - presencial', 'SÃ£o Paulo', NULL),
(9, 'Desenvolvedor mobile', '**DescriÃ§Ã£o do trabalho**\n\nDesenvolver e manter aplicativos para dispositivos mÃ³veis, implementando novas funcionalidades e realizando integraÃ§Ãµes com APIs e serviÃ§os externos.\n\n**Responsabilidades**\n\nDesenvolver e manter aplicativos mobile para Android e iOS, criar novas funcionalidades, integrar APIs REST, corrigir bugs, realizar testes e trabalhar em conjunto com as equipes de desenvolvimento e design.\n\n**Requisitos DesejÃ¡veis**\n\nExperiÃªncia com Ionic, React Native ou Flutter\nConhecimento em JavaScript/TypeScript\nConsumo de APIs REST\nGit\nNoÃ§Ãµes de UX/UI\n\n**RemuneraÃ§Ã£o e BenefÃ­cios**\n\nvale refeiÃ§Ã£o\nvale transporte\nplano de saude \nsalario inicial: 3500R$\n\n**InformaÃ§Ãµes Adicionais**\n\nContrato CLT 5x2 terÃ§a a sabado', 'Aberta', 1, 1, 'junior presencial', 'londrina', NULL);

--
-- Ãndices para tabelas despejadas
--

--
-- Ãndices de tabela `acesso`
--
ALTER TABLE `acesso`
  ADD PRIMARY KEY (`id_acesso`),
  ADD UNIQUE KEY `uq_acesso_vaga_visitante` (`id_vaga`,`visitor_id`),
  ADD KEY `id_vaga` (`id_vaga`);

--
-- Ãndices de tabela `candidato`
--
ALTER TABLE `candidato`
  ADD PRIMARY KEY (`id_candidato`);

--
-- Ãndices de tabela `candidatura`
--
ALTER TABLE `candidatura`
  ADD PRIMARY KEY (`id_candidatura`),
  ADD KEY `fk_candidatura_candidato` (`id_candidato`),
  ADD KEY `fk_candidatura_vaga` (`id_vaga`);

--
-- Ãndices de tabela `empresa`
--
ALTER TABLE `empresa`
  ADD PRIMARY KEY (`id_empresa`);

--
-- Ãndices de tabela `entrevista`
--
ALTER TABLE `entrevista`
  ADD PRIMARY KEY (`id_entrevista`),
  ADD KEY `fk_entrevista_candidatura` (`id_candidatura`),
  ADD KEY `fk_entrevista_rh` (`id_rh`);

--
-- Ãndices de tabela `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id_feedback`);

--
-- Ãndices de tabela `rh`
--
ALTER TABLE `rh`
  ADD PRIMARY KEY (`id_rh`),
  ADD KEY `fk_rh_empresa` (`id_empresa`);

--
-- Ãndices de tabela `vaga`
--
ALTER TABLE `vaga`
  ADD PRIMARY KEY (`id_vaga`),
  ADD KEY `fk_vaga_empresa` (`id_empresa`),
  ADD KEY `fk_vaga_rh` (`id_rh_responsavel`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `acesso`
--
ALTER TABLE `acesso`
  MODIFY `id_acesso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de tabela `candidato`
--
ALTER TABLE `candidato`
  MODIFY `id_candidato` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT de tabela `candidatura`
--
ALTER TABLE `candidatura`
  MODIFY `id_candidatura` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT de tabela `empresa`
--
ALTER TABLE `empresa`
  MODIFY `id_empresa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `entrevista`
--
ALTER TABLE `entrevista`
  MODIFY `id_entrevista` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id_feedback` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `rh`
--
ALTER TABLE `rh`
  MODIFY `id_rh` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `vaga`
--
ALTER TABLE `vaga`
  MODIFY `id_vaga` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- RestriÃ§Ãµes para tabelas despejadas
--

--
-- RestriÃ§Ãµes para tabelas `acesso`
--
ALTER TABLE `acesso`
  ADD CONSTRAINT `acesso_ibfk_1` FOREIGN KEY (`id_vaga`) REFERENCES `vaga` (`id_vaga`);

--
-- RestriÃ§Ãµes para tabelas `candidatura`
--
ALTER TABLE `candidatura`
  ADD CONSTRAINT `fk_candidatura_candidato` FOREIGN KEY (`id_candidato`) REFERENCES `candidato` (`id_candidato`),
  ADD CONSTRAINT `fk_candidatura_vaga` FOREIGN KEY (`id_vaga`) REFERENCES `vaga` (`id_vaga`);

--
-- RestriÃ§Ãµes para tabelas `entrevista`
--
ALTER TABLE `entrevista`
  ADD CONSTRAINT `fk_entrevista_candidatura` FOREIGN KEY (`id_candidatura`) REFERENCES `candidatura` (`id_candidatura`),
  ADD CONSTRAINT `fk_entrevista_rh` FOREIGN KEY (`id_rh`) REFERENCES `rh` (`id_rh`);

--
-- RestriÃ§Ãµes para tabelas `rh`
--
ALTER TABLE `rh`
  ADD CONSTRAINT `fk_rh_empresa` FOREIGN KEY (`id_empresa`) REFERENCES `empresa` (`id_empresa`);

--
-- RestriÃ§Ãµes para tabelas `vaga`
--
ALTER TABLE `vaga`
  ADD CONSTRAINT `fk_vaga_empresa` FOREIGN KEY (`id_empresa`) REFERENCES `empresa` (`id_empresa`),
  ADD CONSTRAINT `fk_vaga_rh` FOREIGN KEY (`id_rh_responsavel`) REFERENCES `rh` (`id_rh`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;


SET UNIQUE_CHECKS=1;
SET FOREIGN_KEY_CHECKS=1;
