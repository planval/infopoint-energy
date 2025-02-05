# infopoint-energy Tools

Welcome to the infopoint-energy Tools Collection!

This repository houses a variety of tools provided by [regiosuisse](https://regiosuisse.ch). 
While the issue tracker is currently disabled, we encourage you to reach out to [our team](https://regiosuisse.ch/regiosuisse-team) with any questions or feedback you may have.

## Installation

    docker compose build
    docker compose up -d
    
    docker exec -it infopoint-energy_php bash -c "composer install"
    docker exec -it infopoint-energy_php bash -c "php bin/console doctrine:migrations:migrate --no-interaction"
    docker exec -it infopoint-energy_php bash -c "yarn install && yarn build"
    docker exec -it infopoint-energy_php bash -c "php bin/console app:user:create"

## Configuration

Available configuration options can be set via `dotenv` and can be overwritten via `.env.local` or directly in the `.env` file. 
Most options are optional, however the following options must be modified in order to run in a production environment:

| Variable            | Description                         |
|---------------------|-------------------------------------|
| `APP_ENV`           | _No description_                    |
| `APP_SECRET`        | _No description_                    |
| `DATABASE_URL`      | Database credentials                |
| `MAILER_DSN`        | Mail server credentials             |
| `MAILER_FROM`       | Mail sender address                 |
| `HOST`              | Host address of production instance |
| `FRONTEND_HOST`     | Host address of primary frontend    |
| `CORS_ALLOW_ORIGIN` | _No description_                    |
| `MAPBOX_API_TOKEN`  | API Token for Mapbox access         |

## Documentation

Currently, we don't have user interface documentation available. However, you can access various technical documents here:

| Name             | URL                                                                                                          |
|------------------|--------------------------------------------------------------------------------------------------------------|
| OpenAPI (UI)     | [http://localhost/api/documentation](http://localhost/api/documentation)                                     |
| OpenAPI          | [http://localhost/api/documentation.json](http://localhost/api/documentation.json)                           |
| Projects Embed   | [http://localhost/embed/projects/documentation.html](http://localhost/embed/projects/documentation.html)     |
| Events Embed     | [http://localhost/embed/events/documentation.html](http://localhost/embed/events/documentation.html)         |
| Jobs Embed       | [http://localhost/embed/jobs/documentation.html](http://localhost/embed/jobs/documentation.html)             |
| Educations Embed | [http://localhost/embed/educations/documentation.html](http://localhost/embed/educations/documentation.html) |
| Regions Embed    | [http://localhost/embed/regions/documentation.html](http://localhost/embed/regions/documentation.html)       |

## Misc

### Authenticate with CHMOS

To authenticate with CHMOS, follow these steps:

1. Obtain your `*.pem` certificate.
2. Place the certificate in the `config/secrets/chmos` directory.

You're now ready to import projects from CHMOS.

### Update GeoJSON

```
cd ./config/gis
ogr2ogr -f GeoJSON -s_srs *.prj -t_srs EPSG:4326 cities.json *.shp
```

### Import cities XLSX

```
php bin/console app:import:cities \
   --municipal-number-column=B \
   --name-column=C \
   --state-column=E \
   *.xlsx
```

### Import regions XLSX

```
php bin/console app:import:regions \
   --municipal-number-column=B \
   --state-column=E \
   --name-columns=E \
   --name-columns=H \
   --name-columns=J \
   --name-columns=K \
   --name-columns=L \
   --name-columns=M \
   --name-columns=N \
   --name-columns=O \
   --name-columns=P \
   --name-columns=Q \
   --type-mapping=cantonal \
   --type-mapping=nrp \
   --type-mapping=ris \
   --type-mapping=ris \
   --type-mapping=ris \
   --type-mapping=ris \
   --type-mapping=ris \
   --type-mapping=ris \
   --type-mapping=intercantonal \
   --type-mapping=energy \
   --remove-orphans \
   *.xlsx
```