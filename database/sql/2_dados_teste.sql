USE car_wash;

-- Registros FICTÍCIOS só para testar. O seed oficial dos serviços é do Dev A.
INSERT INTO usuario (nome, email, senha_hash, perfil) VALUES
  ('Admin Teste', 'admin@teste.local', 'x', 'admin'),
  ('Funcionario Teste', 'func@teste.local', 'x', 'funcionario');

INSERT INTO cliente (nome, telefone, email, senha_hash) VALUES
  ('Cliente Teste', '41999999999', 'cliente@teste.local', 'x');

INSERT INTO endereco (cliente_id, rua, numero, bairro, cidade, cep) VALUES
  (1, 'Rua Teste', '100', 'Centro', 'Curitiba', '80000-000');

INSERT INTO servico (nome, categoria, preco_base, duracao_minutos) VALUES
  ('Servico Teste 60', 'fachada', 100.00, 60),
  ('Servico Teste 90', 'calcada', 150.00, 90),
  ('Servico Teste 120', 'garagem', 200.00, 120);
