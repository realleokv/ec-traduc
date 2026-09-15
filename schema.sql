CREATE DATABASE IF NOT EXISTS ec_traducoes;
USE ec_traducoes;

-- Tabela de Usuários/Administradores
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de Disponibilidade/Eventos da Agenda
CREATE TABLE agenda_disponibilidade (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data_inicio DATETIME NOT NULL,
    data_fim DATETIME NOT NULL,
    status ENUM('disponivel', 'bloqueado', 'ocupado') DEFAULT 'disponivel',
    observacao VARCHAR(255) NULL
);

-- Tabela de Solicitacoes de Agendamento dos Clientes
CREATE TABLE solicitacoes_agendamento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_cliente VARCHAR(100) NOT NULL,
    email_cliente VARCHAR(100) NOT NULL,
    telefone_cliente VARCHAR(20) NOT NULL,
    tipo_servico VARCHAR(100) NOT NULL,
    data_solicitada DATE NOT NULL,
    horario_inicio TIME NOT NULL,
    horario_fim TIME NOT NULL,
    detalhes TEXT NULL,
    status ENUM('pendente', 'aceito', 'recusado') DEFAULT 'pendente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Inserir usuário inicial (Senha padrão: admin123)
-- Importante: Em produção, utilize password_hash() no PHP!
INSERT INTO usuarios (nome, email, senha) 
VALUES ('Elisa', 'elisa.ectraducoes@gmail.com', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe11.758a.56g8mX.cO1PZ1G8I0e4KxCe');