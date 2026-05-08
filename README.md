
# ContaCerta Pix

Sistema para organizar cobranças compartilhadas via Pix.

<img width="1590" height="876" alt="conta-certa1" src="https://github.com/user-attachments/assets/3ef7eae7-c45e-42f3-8387-8fe756ee4f1a" />

## Requisitos

- Docker e Docker Compose
- Node.js e npm

## Rodando localmente com Docker

1. Copie o arquivo de ambiente:

```bash
cp .env.example .env
```

2. Suba os containers:

```bash
docker compose up -d --build
```

3. Instale as dependências do backend:

```bash
docker compose exec app composer install
```

4. Gere a chave da aplicação:

```bash
docker compose exec app php artisan key:generate
```

5. Rode as migrations:

```bash
docker compose exec app php artisan migrate
```

6. Rode o frontend:

```bash
cd frontend
npm install
npm run dev
```

## Acessos locais

- Laravel/API: `http://localhost:8000`
- Frontend/Vite: `http://localhost:5173`
- phpMyAdmin: `http://localhost:8080`

## Testes

Backend:

```bash
docker compose run --rm app php artisan test
```

Frontend:

```bash
cd frontend
npm run test
```

Build:

```bash
cd frontend
npm run build
```

## Observações locais

- Os comprovantes ficam em storage privado local.
- O modo demo está disponível no frontend.
- A criação pública anônima está em standby no momento.
- O PHP local pode não ter todas as extensões do PHPUnit; nesse caso, rode os testes backend no container.

## Documentação auxiliar

- Segurança: `doc/SECURITY.md`
- Contrato da API: `doc/API.md`
