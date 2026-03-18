# Indoor Service Manager

Sistema de gestão operacional desenvolvido em Laravel 12 com Filament 5 para controle de clientes, contratos, domínios, serviços, atividades, relatórios e acompanhamento de tarefas em andamento.

## Tecnologias

- PHP 8.2
- Laravel 12
- Filament 5
- Livewire 4
- MySQL

## Funcionalidades

- Cadastro e gestão de clientes
- Controle de contratos e domínios
- Cadastro de serviços
- Registro de atividades com controle por intervalos de tempo
- Tarefa em andamento com atualização dinâmica no menu
- Dashboard administrativo
- Relatórios operacionais

## Ambiente local

1. Configure o arquivo `.env`
2. Execute as migrations e seeders:

```bash
php artisan migrate:fresh --seed --no-interaction
```

3. Inicie a aplicação:

```bash
php artisan serve
```

## Credenciais padrão

- `admin@indoor.local` / `password`
- `operador@indoor.local` / `password`

## Desenvolvimento

Este sistema foi desenvolvido pela [IndoorTech](https://indoortech.com.br/).

Programador responsável: [Guilherme Lobo](https://www.linkedin.com/in/guilhermelobooliveira/).
