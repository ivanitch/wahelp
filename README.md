# Wahelp API

![Debian](https://img.shields.io/badge/Debian-12-A81D33?logo=debian&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-28.2-2496ED?logo=docker&logoColor=white)
![Nginx](https://img.shields.io/badge/Nginx-1.27-009639?logo=nginx&logoColor=white)
![PHP-FPM](https://img.shields.io/badge/PHP_FPM-7.4-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0.42-4479A1?logo=mysql&logoColor=white)


##  🌟 Установка
```bash
git@github.com:ivanitch/wahelp.git wahelp-api
````

##  🚀 Запуск Docker
```bash
make build && make up && make app

composer install
```

## 🗄️ Миграция БД
```bash
docker exec -i your_db_docker_container mysql -uroot -proot your_dbname -v < /path/to/migrations/init.sql
```

## 👤 Загрузки списка пользователей для рассылки
```bash
make users
```

##  🔗 Описание задачи

Тестовое задание от  команды [Wahelp.ru](http://Wahelp.ru) для будущего backend разработчика:
https://wahelp.notion.site/Wahelp-ru-backend-02b66da3d10b4f818ff7dc16e2138c8c 

