# Sistema de Gestión de Documentos de una Oficina

Plataforma web responsiva para registrar, digitalizar, consultar y dar seguimiento a los
documentos (oficios, circulares, solicitudes, invitaciones y comunicados) que llegan a una
oficina administrativa.

**Universidad Autónoma de Chiapas** · Escuela de Tecnologías Digitales Aplicadas · Campus IV Tapachula
Asignatura: *Desarrollo de aplicaciones web y móviles* · Laboratorio práctico, primer parcial.

---

## 1. Qué resuelve

Cuando los documentos se gestionan solo en papel se traspapelan, se archivan mal y localizar
uno por asunto, número de oficio, remitente o fecha toma demasiado tiempo. Esta aplicación
digitaliza ese flujo completo:

```
recibir → digitalizar → registrar → buscar → actualizar estatus → consultar PDF
```

---

## 2. Tecnologías

| Capa            | Herramienta                                               |
|-----------------|-----------------------------------------------------------|
| Frontend        | HTML5, CSS3, Bootstrap 5.3 y jQuery 3.7                   |
| Backend         | PHP 8 (sin framework)                                      |
| Base de datos   | MySQL / MariaDB con MySQLi y sentencias preparadas         |
| Archivos        | Carpeta `pdf/` + respaldo versionado en GitHub (API REST)  |
| Diseño          | Interfaz responsiva con enfoque *Mobile First*             |

---

## 3. Funcionalidades

### Registro y digitalización
- Alta de documentos con No. de Oficio, Fecha, Asunto, Remitente, Destinatario y Estatus.
- Dos formas de digitalizar: **tomar la fotografía** con la cámara del móvil
  (`capture="environment"`) o **seleccionar un archivo PDF**.
- Las fotografías JPEG se convierten automáticamente a PDF de una página en el servidor
  (`includes/pdf.php`), sin librerías externas, para que todo el acervo quede en un solo formato.
- Validación del tipo real del archivo con `finfo` (no se confía en la extensión) y límite de 10 MB.

### Consulta y seguimiento
- Listado con **buscador**, filtros por estatus y rango de fechas, cinco criterios de
  ordenamiento y **paginación**.
- **Búsqueda avanzada** que combina texto libre, remitente, estatus y rango de fechas.
- **Cambio de estatus por AJAX** (Recibido / En proceso / Atendido) sin recargar la página.
- **Visor de PDF integrado**: el documento se abre en una ventana modal encima del listado,
  dentro de la misma vista; también puede descargarse o abrirse en una pestaña nueva.
- Edición y eliminación de documentos, con confirmación previa.
- **Bitácora** que registra cada alta, modificación y cambio de estatus.

### Panel, reportes y administración
- **Inicio**: indicadores (total, recibidos, en proceso, atendidos), distribución por estatus,
  documentos de la última semana, últimos registros y actividad reciente.
- **Reportes**: documentos por mes, porcentaje de atención, remitentes más frecuentes y
  exportación a **CSV** compatible con Excel.
- **Usuarios**: catálogo del personal con roles (Administrador, Capturista, Consulta).
- **Configuración**: diagnóstico del entorno (MySQL, almacenamiento y respaldo en GitHub).

### Responsividad
- En escritorio: menú lateral fijo y tabla de siete columnas.
- En móvil y tableta: menú deslizable, barra de navegación inferior con botón flotante
  y el listado se transforma en tarjetas apiladas, legibles sin desplazamiento horizontal.

---

## 4. Estructura del proyecto

```
pract4/
├── index.php               Enrutador único: carga el diseño y la vista solicitada
├── config.php              Configuración (lee variables de entorno)
├── conexion.php            Conexión MySQLi
├── ver_pdf.php             Entrega el PDF para el visor modal
├── descargar.php           Entrega el PDF como descarga
├── database.sql            Script de creación de la base de datos y datos de prueba
├── vercel.json             Configuración de despliegue en Vercel
├── includes/
│   ├── funciones.php       Utilidades (escape, CSRF, mensajes, formatos)
│   ├── consultas.php       Consultas SQL preparadas
│   ├── github.php          Respaldo de PDFs mediante la API de GitHub
│   ├── pdf.php             Conversión de fotografía JPEG a PDF
│   ├── header.php          Barra superior y menús
│   └── footer.php          Barra inferior, ventanas modales y scripts
├── vistas/
│   ├── inicio.php          Panel de control
│   ├── documentos.php      Listado con filtros y paginación
│   ├── formulario.php      Alta y edición
│   ├── buscar.php          Búsqueda avanzada
│   ├── reportes.php        Estadísticas y exportación
│   ├── usuarios.php        Catálogo de usuarios
│   ├── configuracion.php   Diagnóstico del entorno
│   ├── _tabla.php          Tabla (escritorio) / tarjetas (móvil)
│   └── _acciones.php       Botones de la columna Acciones
├── acciones/
│   ├── guardar.php         Alta y actualización
│   ├── eliminar.php        Baja
│   ├── estatus.php         Cambio de estatus (responde JSON)
│   ├── usuarios.php        Operaciones del catálogo de usuarios
│   └── exportar.php        Exportación a CSV
├── assets/
│   ├── css/estilos.css
│   └── js/app.js
└── pdf/                    Documentos digitalizados
```

---

## 5. Instalación local (XAMPP)

1. **Copia el proyecto** en `C:\xampp\htdocs\pract4` (o la carpeta equivalente de tu sistema).
2. **Inicia Apache y MySQL** desde el panel de control de XAMPP.
3. **Crea la base de datos**: abre <http://localhost/phpmyadmin>, pestaña *Importar*, y
   selecciona `database.sql`. También puedes ejecutarlo desde la terminal:

   ```bash
   mysql -u root -p < database.sql
   ```

4. **Verifica los permisos** de la carpeta `pdf/` (debe permitir escritura).
5. **Abre la aplicación** en <http://localhost/pract4/>.
6. Entra a **Configuración** en el menú lateral: ahí verás el semáforo de los tres
   componentes (base de datos, almacenamiento y respaldo en GitHub).

Las credenciales por defecto son las de XAMPP (`root` sin contraseña). Para cambiarlas,
define las variables de entorno descritas abajo; no es necesario editar el código.

---

## 6. Variables de entorno

Todas son opcionales: si no se definen, se usan los valores de desarrollo local.

| Variable         | Valor por defecto                      | Para qué sirve                                   |
|------------------|----------------------------------------|--------------------------------------------------|
| `DB_HOST`        | `localhost`                            | Servidor MySQL                                   |
| `DB_PORT`        | `3306`                                 | Puerto                                           |
| `DB_USER`        | `root`                                 | Usuario                                          |
| `DB_PASS`        | *(vacío)*                              | Contraseña                                       |
| `DB_NAME`        | `gestion_documentos`                   | Base de datos                                    |
| `GITHUB_TOKEN`   | *(vacío)*                              | Token con permiso de escritura; activa el respaldo |
| `GITHUB_REPO`    | `usuario123-boop/mis-practicas-web`    | Repositorio destino                              |
| `GITHUB_BRANCH`  | `main`                                 | Rama                                             |
| `GITHUB_PATH`    | `pract4/pdf`                           | Carpeta destino dentro del repositorio           |
| `POR_PAGINA`     | `5`                                    | Registros por página del listado                 |

---

## 7. Respaldo de los PDF en GitHub

Además de guardarse en la carpeta `pdf/`, cada documento digitalizado se envía al
repositorio mediante la **Contents API** de GitHub, de modo que queda versionado junto
con el código fuente y sobrevive aunque el servidor se reinicie.

Para activarlo:

1. En GitHub ve a **Settings → Developer settings → Personal access tokens →
   Fine-grained tokens**, genera uno con permiso **Contents: Read and write** sobre el
   repositorio y cópialo.
2. Define la variable `GITHUB_TOKEN` con ese valor:
   - **En XAMPP**: agrega `SetEnv GITHUB_TOKEN tu_token` al archivo
     `apache\conf\httpd.conf` y reinicia Apache.
   - **En Vercel**: *Settings → Environment Variables*.
3. Vuelve a abrir **Configuración**: el tercer semáforo debe aparecer en verde.

> El token **nunca** se escribe en el código ni se sube al repositorio.
> Si la variable no existe, la aplicación funciona igual, solo sin respaldo remoto.

Cuando un documento tiene respaldo, el menú “⋮” de su fila muestra la opción
**Ver respaldo en GitHub**, y si el archivo local desapareciera, `ver_pdf.php` lo
recupera automáticamente desde el repositorio.

---

## 8. Despliegue en Vercel

El archivo `vercel.json` de la raíz del repositorio ya está configurado: sirve como
páginas estáticas las prácticas 1 y 2, y ejecuta la práctica 4 con el runtime de PHP.

Como Vercel no incluye un servidor MySQL, hay que apuntar la aplicación a una base de
datos en la nube (por ejemplo **Railway**, **Clever Cloud**, **Aiven** o **TiDB Cloud**,
que ofrecen MySQL gratuito):

1. Crea la base de datos en el proveedor que elijas e importa `database.sql`.
2. En Vercel, *Settings → Environment Variables*, captura `DB_HOST`, `DB_PORT`,
   `DB_USER`, `DB_PASS` y `DB_NAME` con los datos de esa base.
3. Captura también `GITHUB_TOKEN`: en Vercel el sistema de archivos es de solo lectura,
   así que el respaldo en GitHub es el almacenamiento real de los PDF.
4. Vuelve a desplegar. La aplicación quedará en `https://<tu-dominio>.vercel.app/pract4/`.

---

## 9. Seguridad aplicada

- **Sentencias preparadas** (`bind_param`) en todas las consultas con datos del usuario.
- **Listas blancas** para los campos de ordenamiento y los nombres de columna, que no
  pueden parametrizarse.
- **Escape de salida** con `htmlspecialchars` en todo lo que se imprime (`e()`).
- **Token CSRF** en cada formulario y en las peticiones AJAX.
- **Validación del tipo MIME real** de los archivos subidos y límite de tamaño.
- Los PDF **no se enlazan directamente**: se sirven a través de `ver_pdf.php`, que valida
  el identificador y controla las cabeceras.
- Las **credenciales** viven en variables de entorno, nunca en el repositorio.

---

## 10. Pruebas realizadas

| # | Prueba                                                     | Resultado |
|---|------------------------------------------------------------|-----------|
| 1 | Alta de documento con PDF adjunto                          | Correcto  |
| 2 | Alta con fotografía JPEG convertida a PDF                  | Correcto  |
| 3 | Visualización del PDF en la ventana modal                  | Correcto  |
| 4 | Descarga del PDF con nombre basado en el No. de oficio     | Correcto  |
| 5 | Cambio de estatus por AJAX y registro en bitácora          | Correcto  |
| 6 | Buscador, filtros por estatus/fecha, orden y paginación    | Correcto  |
| 7 | Edición y eliminación (archivo + registro + bitácora)      | Correcto  |
| 8 | Exportación a CSV con acentos correctos en Excel           | Correcto  |
| 9 | Alta, activación y baja de usuarios; correo duplicado      | Correcto  |
|10 | Rechazo de peticiones sin token CSRF válido (HTTP 403)     | Correcto  |
|11 | Navegación responsiva en móvil, tableta y escritorio       | Correcto  |

---

## 11. Documentación adicional

El reporte técnico completo (solución, arquitectura, modelo de datos, funcionalidades,
pruebas y conclusiones) está en [`REPORTE_TECNICO.md`](REPORTE_TECNICO.md).
