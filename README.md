# 🎯 Tumanag3r — Prueba Técnica Symfony

Aplicación de consola Symfony para la gestión de campañas e influencers.

---

## 🚀 Características

- 📦 Crear campañas desde la consola
- 📋 Listar campañas con detalles
- 🌟 (Bonus) Asignar influencers a campañas
- 🧪 Base de datos PostgreSQL
- ⚙️ Estructura limpia con Symfony Console + Doctrine

---

## 🛠️ Requisitos

- PHP 8.1 o superior
- Composer
- Symfony CLI
- PostgreSQL 13+
- Extensiones PHP: `pdo_pgsql`, `pgsql`, `intl`, `mbstring`

---

## ⚙️ Instalación

```bash
# Clona o descarga el proyecto
cd Php-Symphony/

# Instala las dependencias
composer install
```

## 🔧 Configuración
1. Edita el archivo .env.local
```dotrenv
DATABASE_URL="postgresql://<user>:<password>@127.0.0.1:5432/db_name?serverVersion=15&charset=utf8"
```
He usado PostgreSQL como base de datos, asegúrate de tenerla instalada y configurada.

2. Crea y migra la base de datos
```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```
3. Inserta datos de prueba
```sql
    INSERT INTO influencers (name, email, followers_count) VALUES
    ('Influencer 1', 'influencer1@email.com', 10000),
    ('Influencer 2', 'influencer2@email.com', 15000),
    ('Influencer 3', 'influencer3@email.com', 20000),
    ('Influencer 4', 'influencer4@email.com', 12000),
    ('Influencer 5', 'influencer5@email.com', 18000);
```

##  🖥️ Comandos disponibles
```bash
php bin/console app:create-campaign
``

## Listar campañas
```bash
php bin/console app:list-campaigns
```

## Asignar influencers a una campaña
```bash
php bin/console app:assign-influencers <campaign_id> <influencer_id>
```

## 📬 Contacto
Desarrollado por Yash para evaluación técnica.
Para cualquier duda, puedes contactar conmigo a través de GitHub o LinkedIn.