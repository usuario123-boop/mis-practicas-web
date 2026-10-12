# Despliegue en Vercel

La práctica funciona igual en XAMPP y en Vercel. El código detecta el entorno
con la variable `VERCEL` y cambia dos cosas: dónde guarda los PDF y dónde
guarda las sesiones.

| | XAMPP | Vercel |
|---|---|---|
| Base de datos | MySQL local | MySQL en la nube (TLS) |
| PDF | carpeta `pdf/` | repositorio de GitHub |
| Sesiones | archivos en disco | tabla `sesiones` de MySQL |
| Tamaño máximo | 10 MB | 4 MB (tope de la plataforma: 4.5 MB) |

## 1. Archivos en la raíz del repositorio

`vercel.json` y `php.ini` van en la raíz de `mis-practicas-web`, no dentro de
`pract4/`.

## 2. Base de datos

Crea una MySQL en la nube (Aiven, Clever Cloud, Railway…) e importa
`database_nube.sql`. Es el mismo esquema que `database.sql` pero sin
`CREATE DATABASE`, sin `USE` y sin el índice `FULLTEXT`.

## 3. Almacenamiento de los PDF

En Vercel el disco es de solo lectura y lo que se escriba en `/tmp` se pierde
al terminar la petición, así que GitHub deja de ser respaldo y pasa a ser el
almacenamiento real. Sin `GITHUB_TOKEN` la aplicación rechaza las altas en
lugar de registrar documentos cuyo archivo no existiría.

Crea una rama aparte para los archivos:

```
git branch archivos && git push origin archivos
```

Si los PDF se suben a `main`, cada documento registrado genera un commit y
cada commit dispara un despliegue nuevo.

## 4. Variables de entorno

En Vercel → Settings → Environment Variables:

| Variable | Valor |
|---|---|
| `DB_HOST` | host de la base en la nube |
| `DB_PORT` | puerto (suele no ser 3306) |
| `DB_USER` | usuario |
| `DB_PASS` | contraseña |
| `DB_NAME` | nombre de la base |
| `GITHUB_TOKEN` | token personal con permiso de escritura |
| `GITHUB_REPO` | `usuario123-boop/mis-practicas-web` |
| `GITHUB_BRANCH` | `archivos` |
| `GITHUB_PATH` | `pract4/pdf` |

Opcionales: `DB_SSL` (`1` en Vercel, `0` en local), `DB_SSL_CA` con la ruta al
certificado si el proveedor exige verificarlo, `MAX_PDF_BYTES`, `POR_PAGINA`.

## 5. Comprobación

Abre `/pract4/index.php?vista=configuracion`. Los cuatro semáforos —base de
datos, almacenamiento, repositorio y extensiones de PHP— deben estar en verde.
