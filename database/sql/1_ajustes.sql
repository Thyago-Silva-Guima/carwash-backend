USE car_wash;

-- cliente: login por e-mail e senha, aceite de termos (LGPD)
ALTER TABLE cliente
  MODIFY email VARCHAR(100) NOT NULL,
  MODIFY tipo VARCHAR(30) NOT NULL DEFAULT 'residencial',
  ADD COLUMN senha_hash VARCHAR(255) NOT NULL AFTER email,
  ADD COLUMN aceite_termos_em DATETIME NULL,
  ADD UNIQUE KEY uq_cliente_email (email);

-- endereco: número, bairro, complemento
ALTER TABLE endereco
  ADD COLUMN numero VARCHAR(20) NULL AFTER rua,
  ADD COLUMN bairro VARCHAR(80) NULL AFTER numero,
  ADD COLUMN complemento VARCHAR(100) NULL AFTER bairro;

-- servico: duração (60 = valor PROVISÓRIO), descrição e ativo
ALTER TABLE servico
  ADD COLUMN descricao VARCHAR(255) NULL,
  ADD COLUMN duracao_minutos INT NOT NULL DEFAULT 60,
  ADD COLUMN ativo TINYINT(1) NOT NULL DEFAULT 1;

-- agendamento: fim, observações, motivo, Google e datas de controle
ALTER TABLE agendamento
  ADD COLUMN data_hora_fim DATETIME NOT NULL AFTER data_hora,
  ADD COLUMN observacoes VARCHAR(500) NULL,
  ADD COLUMN motivo VARCHAR(255) NULL,
  ADD COLUMN google_event_id VARCHAR(255) NULL,
  ADD COLUMN created_at TIMESTAMP NULL DEFAULT NULL,
  ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL,
  ADD INDEX idx_agendamento_data_hora (data_hora);

-- agendamento_servico: preço da época
ALTER TABLE agendamento_servico
  ADD COLUMN preco_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00;

-- fotos enviadas pelo cliente (a tabela "foto" continua sendo antes/depois)
CREATE TABLE anexo_agendamento (
  id INT PRIMARY KEY AUTO_INCREMENT,
  agendamento_id INT NOT NULL,
  url VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (agendamento_id) REFERENCES agendamento(id) ON DELETE CASCADE
);
