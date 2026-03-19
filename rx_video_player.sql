-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Mar 20, 2026 at 01:09 AM
-- Server version: 10.11.10-MariaDB-log
-- PHP Version: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rx_video_player`
--

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT 'TV-Desconocido',
  `client_key` char(32) NOT NULL,
  `orientation` enum('horizontal','vertical') DEFAULT 'horizontal',
  `background` varchar(255) DEFAULT NULL,
  `last_ping` datetime DEFAULT NULL,
  `active` tinyint(1) DEFAULT 0,
  `playlist_version` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `name`, `client_key`, `orientation`, `background`, `last_ping`, `active`, `playlist_version`) VALUES
(10, 'Firefox-LenovoTab', '46URVtpy3BpXadVl66l3QXyLPSEKzQ3h', 'horizontal', '', '2026-03-18 09:15:59', 1, 4),
(11, 'Firefox-RataxLpt', 'MP0D7JHLbL2UhZgEhPYDpNeX2P6alrkt', 'horizontal', '', '2026-03-18 10:41:09', 1, 6),
(12, 'Chrome-LenovoTab', 'CbAtwnga4wQKBOCATAv27dp9Dz9foYhH', 'horizontal', '', '2026-03-17 23:58:13', 1, 1),
(13, 'TV-4f45ce76', '0gtMUJQ7vgigWGq1MTaZ5jkEyLBPiNxK', 'horizontal', '', '2026-03-18 09:19:55', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `empresas`
--

CREATE TABLE `empresas` (
  `id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sucursales`
--

CREATE TABLE `sucursales` (
  `id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `oauth_provider` enum('google') DEFAULT 'google',
  `oauth_id` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `empresa_id` int(11) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `usuario_empresa`
--

CREATE TABLE `usuario_empresa` (
  `usuario_id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `usuario_sucursal`
--

CREATE TABLE `usuario_sucursal` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `sucursal_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `videos`
--

CREATE TABLE `videos` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `thumbnail` varchar(255) NOT NULL,
  `upload_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `videos`
--

INSERT INTO `videos` (`id`, `title`, `filename`, `thumbnail`, `upload_date`) VALUES
(1, 'Prueba 01', '1773800263_screen-recording.mp4', '1773800399_thumb.jpg', '2026-03-17 23:17:52'),
(2, 'Test 02', '1773800741_Videocapturadepantalla_20260310_194701.mp4', '1773800741_thumb.jpg', '2026-03-17 23:25:45'),
(3, 'Test 03', '1773800761_Videocapturadepantalla_20260310_195202.mp4', '1773800761_thumb.jpg', '2026-03-17 23:26:06'),
(4, 'Test 04', '1773800784_Videocapturadepantalla_20260310_193345.mp4', '1773800784_thumb.jpg', '2026-03-17 23:26:28');

-- --------------------------------------------------------

--
-- Table structure for table `video_client`
--

CREATE TABLE `video_client` (
  `id` int(11) NOT NULL,
  `video_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `play_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `video_client`
--

INSERT INTO `video_client` (`id`, `video_id`, `client_id`, `play_order`) VALUES
(12, 4, 12, 1),
(13, 2, 12, 2),
(24, 4, 11, 1),
(25, 2, 11, 2),
(26, 1, 11, 3),
(27, 4, 10, 1),
(28, 2, 10, 2),
(29, 1, 10, 3),
(30, 1, 13, 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `client_key` (`client_key`);

--
-- Indexes for table `empresas`
--
ALTER TABLE `empresas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sucursales`
--
ALTER TABLE `sucursales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `empresa_id` (`empresa_id`);

--
-- Indexes for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `oauth_id` (`oauth_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `empresa_id` (`empresa_id`);

--
-- Indexes for table `usuario_empresa`
--
ALTER TABLE `usuario_empresa`
  ADD PRIMARY KEY (`usuario_id`,`empresa_id`),
  ADD KEY `empresa_id` (`empresa_id`);

--
-- Indexes for table `usuario_sucursal`
--
ALTER TABLE `usuario_sucursal`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_perm` (`usuario_id`,`sucursal_id`),
  ADD KEY `sucursal_id` (`sucursal_id`);

--
-- Indexes for table `videos`
--
ALTER TABLE `videos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_title` (`title`);

--
-- Indexes for table `video_client`
--
ALTER TABLE `video_client`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_assign` (`video_id`,`client_id`),
  ADD KEY `client_id` (`client_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `empresas`
--
ALTER TABLE `empresas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sucursales`
--
ALTER TABLE `sucursales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `usuario_sucursal`
--
ALTER TABLE `usuario_sucursal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `videos`
--
ALTER TABLE `videos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `video_client`
--
ALTER TABLE `video_client`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `sucursales`
--
ALTER TABLE `sucursales`
  ADD CONSTRAINT `sucursales_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `usuario_empresa`
--
ALTER TABLE `usuario_empresa`
  ADD CONSTRAINT `usuario_empresa_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `usuario_empresa_ibfk_2` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `usuario_sucursal`
--
ALTER TABLE `usuario_sucursal`
  ADD CONSTRAINT `usuario_sucursal_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `usuario_sucursal_ibfk_2` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `video_client`
--
ALTER TABLE `video_client`
  ADD CONSTRAINT `video_client_ibfk_1` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `video_client_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
