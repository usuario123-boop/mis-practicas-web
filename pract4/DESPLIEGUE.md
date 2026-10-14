# Despliegue en Vercel

La práctica funciona igual en XAMPP y en Vercel. El código detecta el entorno
con la variable `VERCEL` y cambia dos cosas: dónde guarda las sesiones y si
escribe o no una copia de los PDF en el disco.

| | XAMPP | Vercel |
|---|---|---|
| Base de datos | MySQL local | MySQL en la nube (TLS) |
| PDF | columna `documentos.archivo_datos` + copia en `pdf/` | solo la columna `archivo_datos` |
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

El archivo se guarda dentro de MySQL, en `documentos.archivo_datos`. No hace
falta ningún servicio extra ni token: en Vercel el disco es de solo lectura y
lo que se escriba en `/tmp` se pierde al terminar la petición, así que la base
de datos es el único almacenamiento posible, y al borrar un documento su PDF
desaparece con la fila.

En XAMPP se escribe además una copia en `pract4/pdf/`, con el mismo nombre con
el que se descarga el documento. Es solo una comodidad para abrir los archivos
desde el explorador: la aplicación siempre lee el PDF de la base de datos. Esa
carpeta no se sincroniza con el repositorio (basta el `.gitkeep`).

## 4. Variables de entorno

En Vercel → Settings → Environment Variables:

| Variable | Valor |
|---|---|
| `DB_HOST` | host de la base en la nube |
| `DB_PORT` | puerto (suele no ser 3306) |
| `DB_USER` | usuario |
| `DB_PASS` | contraseña |
| `DB_NAME` | nombre de la base |

Opcionales: `DB_SSL` (`1` en Vercel, `0` en local), `DB_SSL_CA` con la ruta al
certificado si el proveedor exige verificarlo, `MAX_PDF_BYTES`, `POR_PAGINA`.

## 5. La cámara en el teléfono

La foto se toma dentro de la página con `getUserMedia` y se reduce a 1600 px de
lado mayor antes de subirla. Así el JPEG pesa unos cientos de kilobytes, cabe
en el límite de 4.5 MB de Vercel y se evita el error «memoria insuficiente»
que devuelven los teléfonos con poca memoria libre cuando la app de cámara
tiene que entregar una foto a resolución completa. `getUserMedia` exige un
origen seguro: en Vercel hay HTTPS y `localhost` cuenta como seguro, pero si se
abre la práctica por IP de la red local (`http://192.168.x.x/...`) el navegador
no dará acceso a la cámara y la aplicación recurrirá al selector del sistema.

## 6. Comprobación

Abre `/pract4/index.php?vista=configuracion`. Los semáforos —base de datos,
almacenamiento y extensiones de PHP— deben estar en verde.
