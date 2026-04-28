# Sistema de Ponto

Sistema de controle de ponto eletrônico em PHP/MySQL, desenvolvido para uso com XAMPP ou servidor web local/compartilhado.

## Funcionalidades

- Registro de ponto via **QR Code** (câmera) ou **matrícula manual**
- Cadastro e gerenciamento de funcionários
- Dashboard de horas trabalhadas com filtro por funcionário e período
- Listagem de registros com filtros por nome e data
- Geração de **Relatório PDF** com pares de entrada/saída por dia e totais
- Alternância de situação (Ativo / Inativo) diretamente na listagem
- Edição de funcionários via modal sem sair da página

## Requisitos

- PHP 7.4+ ou 8.x
- MySQL 5.7+ / MariaDB 10.x
- Composer (para dependências PHP)
- Servidor web (Apache via XAMPP, nginx, etc.)
- Extensão `pdo_mysql` habilitada no PHP

## Instalação

1. **Clone ou copie** o projeto para a pasta do servidor web (ex: `C:\xampp\htdocs\projeto-ponto`).
2. **Instale as dependências** PHP:
   ```bash
   composer install
   ```
3. **Configure o banco de dados** em `conexao.php` (já configurado para o ambiente de produção).
4. **Importe o banco** no MySQL conforme o schema da aplicação.
5. Acesse pelo navegador: `http://localhost/projeto-ponto/`

## Estrutura de arquivos

| Arquivo | Descrição |
|---|---|
| `index.php` | Menu principal |
| `ponto.php` | Tela de registro via QR Code ou matrícula |
| `registrar_ponto.php` | Backend — processa registro de ponto (JSON) |
| `registros.php` | Listagem de registros com filtros |
| `dashboard_ponto.php` | Dashboard de horas trabalhadas |
| `gerar_relatorio.php` | Geração de relatório PDF |
| `cadastrar_funcionario.php` | Formulário de cadastro de funcionário |
| `processa_cadastro.php` | Backend — persiste novo funcionário |
| `gerenciar_funcionarios.php` | Listagem, edição e controle de situação |
| `atualiza_funcionario.php` | Backend — atualiza dados do funcionário |
| `mudar_situacao.php` | Backend — altera Ativo/Inativo |
| `gerar_qrcode.php` | Gera imagem QR Code para a matrícula do funcionário |
| `conexao.php` | Configuração da conexão PDO com MySQL |

## Banco de dados

O sistema utiliza pelo menos as seguintes tabelas:

**`funcionarios`** — cadastro dos colaboradores  
**`registro_ponto`** — log de entradas e saídas (tipo: `entrada` | `saida`)

## Relatório PDF

O relatório pode ser gerado de três formas:
1. **Menu principal** → "Relatório PDF" (todos os funcionários, todos os registros)
2. **Dashboard** → filtrar por funcionário e/ou período → "Imprimir / Exportar Relatório PDF"
3. URL direta: `gerar_relatorio.php?nome=João&data_inicio=2025-01-01&data_fim=2025-01-31`

O PDF exibe, para cada funcionário, uma tabela com pares de entrada/saída por dia e o total de horas trabalhadas no período. Entradas sem saída correspondente são marcadas como **Em aberto**.

## Tecnologias utilizadas

- PHP (PDO)
- MySQL
- Bootstrap 5.3
- Bootstrap Icons 1.11
- [endroid/qr-code](https://github.com/endroid/qr-code) via Composer
- [FPDF](http://www.fpdf.org/) para geração de PDF
- [html5-qrcode](https://github.com/mebjas/html5-qrcode) (CDN) para leitura de QR Code via câmera
