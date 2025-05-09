-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Tempo de geração: 08/05/2025 às 23:39
-- Versão do servidor: 10.11.10-MariaDB-cll-lve
-- Versão do PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `zfgwmhfz_natura`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `arquivos`
--

CREATE TABLE `arquivos` (
  `id` int(11) NOT NULL,
  `tipo` enum('musica','comercial') NOT NULL,
  `nome` varchar(255) NOT NULL,
  `caminho` varchar(255) NOT NULL,
  `data_upload` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `arquivos`
--

INSERT INTO `arquivos` (`id`, `tipo`, `nome`, `caminho`, `data_upload`) VALUES
(63, 'musica', 'musica 1.mp3', '/uploads/musicas/musica 1.mp3', '2025-05-05 00:59:51'),
(64, 'musica', 'musica 2.mp3', '/uploads/musicas/musica 2.mp3', '2025-05-05 00:59:51'),
(65, 'musica', 'musica 3.mp3', '/uploads/musicas/musica 3.mp3', '2025-05-05 00:59:53'),
(66, 'musica', 'musica 4.mp3', '/uploads/musicas/musica 4.mp3', '2025-05-05 00:59:53'),
(67, 'musica', 'musica 5.mp3', '/uploads/musicas/musica 5.mp3', '2025-05-05 00:59:54'),
(68, 'musica', 'musica 6.mp3', '/uploads/musicas/musica 6.mp3', '2025-05-05 00:59:54'),
(69, 'musica', 'musica 7.mp3', '/uploads/musicas/musica 7.mp3', '2025-05-05 00:59:57'),
(70, 'comercial', 'comercial 1.mp3', '/uploads/comerciais/comercial 1.mp3', '2025-05-05 01:00:36'),
(71, 'comercial', 'comercial 2.mp3', '/uploads/comerciais/comercial 2.mp3', '2025-05-05 01:00:38'),
(72, 'comercial', 'comercial 3.mp3', '/uploads/comerciais/comercial 3.mp3', '2025-05-05 01:00:38'),
(73, 'comercial', 'comercial 4.mp3', '/uploads/comerciais/comercial 4.mp3', '2025-05-05 01:00:38'),
(74, 'comercial', 'comercial 5.mp3', '/uploads/comerciais/comercial 5.mp3', '2025-05-05 01:00:38'),
(75, 'comercial', 'comercial 6.mp3', '/uploads/comerciais/comercial 6.mp3', '2025-05-05 01:00:38'),
(76, 'comercial', 'comercial 7.mp3', '/uploads/comerciais/comercial 7.mp3', '2025-05-05 01:00:38'),
(77, 'comercial', 'comercial 8.mp3', '/uploads/comerciais/comercial 8.mp3', '2025-05-05 01:00:38'),
(78, 'comercial', 'comercial 9.mp3', '/uploads/comerciais/comercial 9.mp3', '2025-05-05 01:00:38'),
(79, 'musica', 'aaaa.mp3', '/uploads/musicas/aaaa.mp3', '2025-05-05 07:22:15'),
(80, 'musica', 'calcinhapreta-dois-amoresduas-paixoes-0f5793e4.mp3', '/uploads/musicas/calcinhapreta-dois-amoresduas-paixoes-0f5793e4.mp3', '2025-05-05 08:39:01');

-- --------------------------------------------------------

--
-- Estrutura para tabela `playlists`
--

CREATE TABLE `playlists` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `caminho` varchar(255) NOT NULL,
  `intervalo_musicas` int(11) NOT NULL DEFAULT 0,
  `horario_inicio` time DEFAULT NULL,
  `horario_fim` time DEFAULT NULL,
  `dias_semana` varchar(255) DEFAULT NULL,
  `data_criacao` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `playlists`
--

INSERT INTO `playlists` (`id`, `nome`, `caminho`, `intervalo_musicas`, `horario_inicio`, `horario_fim`, `dias_semana`, `data_criacao`) VALUES
(27, '02', '/home/zfgwmhfz/domains/webradiogratis.x10.mx/public_html/includes/../playlists/02_27.m3u', 0, '23:42:00', '23:59:00', 'seg,ter,qua,qui,sex,sab,dom', '2025-05-05 01:02:21'),
(28, 'default7', '/home/zfgwmhfz/domains/webradiogratis.x10.mx/public_html/includes/../playlists/default7_28.m3u', 0, '00:00:00', '23:59:59', '', '2025-05-05 07:20:30'),
(29, '01', '/home/zfgwmhfz/domains/webradiogratis.x10.mx/public_html/includes/../playlists/01_29.m3u', 0, '00:00:00', '23:42:00', 'seg,ter,qua,qui,sex,sab,dom', '2025-05-05 08:46:41');

-- --------------------------------------------------------

--
-- Estrutura para tabela `playlist_arquivos`
--

CREATE TABLE `playlist_arquivos` (
  `id` int(11) NOT NULL,
  `playlist_id` int(11) NOT NULL,
  `arquivo_id` int(11) NOT NULL,
  `ordem` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `playlist_arquivos`
--

INSERT INTO `playlist_arquivos` (`id`, `playlist_id`, `arquivo_id`, `ordem`) VALUES
(723, 28, 70, 0),
(724, 28, 73, 1),
(725, 28, 71, 2),
(726, 28, 72, 3),
(1033, 27, 63, 0),
(1034, 27, 63, 1),
(1035, 27, 70, 2),
(1036, 27, 70, 3),
(1037, 27, 63, 4),
(1038, 27, 71, 5),
(1039, 27, 64, 6),
(1040, 27, 72, 7),
(1041, 27, 65, 8),
(1042, 27, 73, 9),
(1043, 27, 66, 10),
(1044, 27, 74, 11),
(1045, 27, 67, 12),
(1046, 27, 75, 13),
(1047, 27, 68, 14),
(1048, 27, 76, 15),
(1049, 27, 69, 16),
(1050, 27, 77, 17),
(1051, 27, 78, 18),
(1052, 29, 63, 0),
(1053, 29, 70, 1),
(1054, 29, 64, 2),
(1055, 29, 71, 3),
(1056, 29, 65, 4),
(1057, 29, 72, 5),
(1058, 29, 66, 6),
(1059, 29, 73, 7),
(1060, 29, 67, 8),
(1061, 29, 74, 9),
(1062, 29, 68, 10),
(1063, 29, 75, 11),
(1064, 29, 69, 12),
(1065, 29, 76, 13),
(1066, 29, 63, 14),
(1067, 29, 77, 15),
(1068, 29, 64, 16),
(1069, 29, 78, 17);

-- --------------------------------------------------------

--
-- Estrutura para tabela `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`) VALUES
(1, 'admin', '$2y$10$wlE8gJ.ZkrdZW2gcTX11p.Gs/pDc1xKipZCkosbfgjsBxP37KpQFO');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `arquivos`
--
ALTER TABLE `arquivos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tipo` (`tipo`),
  ADD KEY `idx_caminho` (`caminho`),
  ADD KEY `idx_nome` (`nome`),
  ADD KEY `idx_data_upload` (`data_upload`);

--
-- Índices de tabela `playlists`
--
ALTER TABLE `playlists`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_horario` (`horario_inicio`,`horario_fim`),
  ADD KEY `idx_nome` (`nome`);

--
-- Índices de tabela `playlist_arquivos`
--
ALTER TABLE `playlist_arquivos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `playlist_id` (`playlist_id`),
  ADD KEY `arquivo_id` (`arquivo_id`),
  ADD KEY `idx_playlist_arquivo` (`playlist_id`,`arquivo_id`);

--
-- Índices de tabela `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `arquivos`
--
ALTER TABLE `arquivos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT de tabela `playlists`
--
ALTER TABLE `playlists`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT de tabela `playlist_arquivos`
--
ALTER TABLE `playlist_arquivos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1070;

--
-- AUTO_INCREMENT de tabela `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `playlist_arquivos`
--
ALTER TABLE `playlist_arquivos`
  ADD CONSTRAINT `fk_arquivo_id` FOREIGN KEY (`arquivo_id`) REFERENCES `arquivos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_playlist_id` FOREIGN KEY (`playlist_id`) REFERENCES `playlists` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
