# Reporte técnico
## Sistema de Gestión de Documentos de una Oficina

**Universidad Autónoma de Chiapas**
Escuela de Tecnologías Digitales Aplicadas · Campus IV Tapachula
Licenciatura en Ingeniería en Desarrollo y Tecnologías de Software
Asignatura: Desarrollo de aplicaciones web y móviles
Laboratorio práctico · Primer parcial

---

## 1. Problemática y solución propuesta

En una oficina administrativa se reciben a diario oficios, circulares, solicitudes,
invitaciones y comunicados. Cuando ese flujo se gestiona únicamente en papel aparecen
tres problemas: los documentos se traspapelan, se archivan en categorías incorrectas y
localizar uno por asunto, número de oficio, remitente o fecha consume demasiado tiempo.
El resultado es un seguimiento tardío y un riesgo real de extravío de información.

La solución desarrollada es una **plataforma web responsiva** que digitaliza el ciclo
completo del documento y lo mantiene consultable desde cualquier dispositivo:

```
recibir  →  digitalizar  →  registrar  →  buscar  →  actualizar estatus  →  consultar PDF
```

El personal captura el documento desde el teléfono —fotografiándolo con la cámara— o
desde la computadora adjuntando un PDF. A partir de ese momento el documento existe como
un registro consultable, con su archivo asociado y un estatus que refleja en qué punto
del proceso se encuentra.

---

## 2. Arquitectura

La aplicación sigue una arquitectura **cliente–servidor de tres capas**, sin framework,
tal como pide el laboratorio.

```
┌──────────────────────────── NAVEGADOR (cliente) ─────────────────────────────┐
│  HTML5 semántico  ·  CSS3 + Bootstrap 5.3  ·  jQuery 3.7                     │
│  · Diseño responsivo Mobile First                                            │
│  · Vista previa y validación del archivo antes de enviarlo                   │
│  · Visor de PDF en ventana modal (iframe)                                    │
│  · Cambio de estatus por AJAX                                                │
└───────────────────────────────────┬──────────────────────────────────────────┘
                                    │  HTTP (GET / POST / AJAX-JSON)
┌───────────────────────────────────▼──────────────────────────────────────────┐
│                        SERVIDOR DE APLICACIÓN · PHP 8                        │
│                                                                              │
│   index.php  ── enrutador: ?vista= decide qué pantalla se dibuja             │
│       ├── includes/   infraestructura (funciones, consultas, github, pdf)    │
│       ├── vistas/     presentación (solo HTML + llamadas a consultas)        │
│       └── acciones/   lógica de escritura (guardar, eliminar, estatus…)      │
│                                                                              │
│   ver_pdf.php / descargar.php ── entrega controlada de los archivos          │
└──────────┬────────────────────────────────────────────┬──────────────────────┘
           │                                            │
┌──────────▼──────────────┐              ┌──────────────▼────────────────────┐
│   MySQL / MariaDB       │              │   Almacenamiento de archivos      │
│   · documentos          │              │   · carpeta pdf/ (local)          │
│   · usuarios            │              │   · respaldo en GitHub (API REST) │
│   · bitacora            │              └───────────────────────────────────┘
└─────────────────────────┘
```

### Separación de responsabilidades

| Carpeta     | Responsabilidad                                                        |
|-------------|------------------------------------------------------------------------|
| `includes/` | Infraestructura reutilizable: utilidades, consultas SQL, integración con GitHub, generación de PDF, cabecera y pie. |
| `vistas/`   | Solo presentación. Reciben datos ya consultados y los dibujan.         |
| `acciones/` | Solo escritura. Validan, modifican el estado y redirigen con un mensaje.|
| `assets/`   | Hoja de estilos y lógica de interfaz.                                  |

Esta separación evita el patrón habitual de un archivo PHP enorme con HTML y SQL
mezclados: cada archivo tiene un motivo único para cambiar, y el mismo fragmento
`vistas/_tabla.php` se reutiliza en el panel de inicio, el listado y la búsqueda avanzada.

### Enrutamiento

Toda la navegación pasa por `index.php?vista=<sección>`. El enrutador valida la vista
solicitada contra una lista blanca y carga el archivo correspondiente dentro del mismo
diseño. Dos consecuencias prácticas: no se puede incluir un archivo arbitrario mediante
la URL, y la cabecera, el menú y el pie se escriben una sola vez.

---

## 3. Modelo de datos

### Tabla `documentos`

| Campo            | Tipo                                      | Descripción                        |
|------------------|-------------------------------------------|------------------------------------|
| `id`             | INT, PK, AUTO_INCREMENT                   | Identificador único                |
| `no_oficio`      | VARCHAR(50), NOT NULL                     | Número o folio                     |
| `fecha`          | DATE, NOT NULL                            | Fecha de recepción                 |
| `asunto`         | VARCHAR(255), NOT NULL                    | Descripción breve                  |
| `remitente`      | VARCHAR(150), NOT NULL                    | Quien remite                       |
| `destinatario`   | VARCHAR(150), NOT NULL                    | Área destinataria                  |
| `estatus`        | ENUM('Recibido','En proceso','Atendido')  | Etapa del seguimiento              |
| `archivo_pdf`    | VARCHAR(255), NOT NULL                    | Ruta relativa del archivo          |
| `archivo_nombre` | VARCHAR(255)                              | Nombre dentro de `pdf/`            |
| `archivo_tam`    | INT                                       | Tamaño en bytes                    |
| `github_url`     | VARCHAR(500)                              | URL del respaldo versionado        |
| `creado_en`      | TIMESTAMP                                 | Alta del registro                  |
| `actualizado_en` | TIMESTAMP ON UPDATE                       | Última modificación                |

**Índices:** `no_oficio`, `fecha`, `estatus`, `remitente` y `destinatario`, más un índice
`FULLTEXT` sobre los cuatro campos de texto. Están elegidos a partir de las consultas
reales de la aplicación: el buscador recorre esos cuatro campos y los filtros del listado
operan sobre estatus y rango de fechas.

El campo `estatus` es un `ENUM` y no texto libre: la base de datos rechaza cualquier valor
distinto de los tres permitidos, lo que garantiza la integridad aunque se escribiera desde
fuera de la aplicación.

### Tabla `usuarios`

Personal que opera la plataforma: `nombre`, `correo` (índice único), `area`, `rol`
(`Administrador` / `Capturista` / `Consulta`) y `activo`.

### Tabla `bitacora`

Trazabilidad de cada documento: `documento_id`, `accion`, `detalle` y `registrado_en`.
Tiene una clave foránea hacia `documentos` con `ON DELETE CASCADE`, de modo que al
eliminar un documento su historial desaparece con él y no quedan registros huérfanos.

El motor es **InnoDB** precisamente porque soporta claves foráneas, y la codificación es
`utf8mb4_unicode_ci` para que los acentos y la ñ se almacenen y ordenen correctamente.

---

## 4. Funcionalidades implementadas

### 4.1 Registro y digitalización

El formulario captura los seis campos del modelo mínimo y ofrece dos rutas de
digitalización, igual que en la interfaz de referencia:

- **Tomar foto del documento** — usa un `<input type="file" accept="image/*"
  capture="environment">`, que en un teléfono abre directamente la cámara trasera.
- **Seleccionar archivo PDF** — un selector de archivos convencional.

Ambos botones comparten la vista previa (nombre, tamaño, icono según el tipo) y el botón
para quitar el archivo elegido.

**Conversión de fotografía a PDF.** El requisito pide asociar la captura *como PDF*. Las
fotografías JPEG se incrustan en un PDF de una página tamaño carta directamente en el
servidor (`includes/pdf.php`), aprovechando que el formato PDF admite datos JPEG mediante
el filtro `DCTDecode`. La imagen no se recomprime —se copia tal cual dentro del
documento— y no se necesita ninguna librería externa ni la extensión GD. Así todo el
acervo queda en un único formato, se visualice como se visualice.

### 4.2 Consulta, filtros y seguimiento

- **Buscador** por coincidencia parcial en número de oficio, asunto, remitente y destinatario.
- **Filtros** por estatus y rango de fechas, con indicador de los filtros activos y la
  opción de limpiarlos de un clic.
- **Cinco criterios de ordenamiento** y **paginación** con ventana deslizante de páginas.
- **Búsqueda avanzada** en su propia sección, que añade el filtro por remitente y permite
  afinar los resultados ya cargados sin volver al servidor.
- **Cambio de estatus por AJAX**: desde el menú “⋮” de cada fila, la etiqueta se actualiza
  en el momento y aparece un aviso emergente. No hay recarga de página.

### 4.3 Visualización del PDF dentro de la plataforma

El requisito pide consultar el PDF **dentro** de la plataforma. Al pulsar el icono del ojo
se abre una **ventana modal sobre la misma vista** que contiene un `<iframe>` apuntando a
`ver_pdf.php?id=…`. El usuario no cambia de pantalla ni pierde los filtros que tenía
aplicados: cierra la ventana y continúa justo donde estaba.

Dos detalles de implementación:

- El `src` del `iframe` se asigna al abrir y se limpia al cerrar. Si se dejara fijo, el
  navegador mantendría en memoria todos los PDF abiertos durante la sesión.
- La cabecera de la ventana incluye el número de oficio, el asunto y accesos directos para
  descargar o abrir en pestaña nueva, por si el usuario prefiere la vista completa.

`ver_pdf.php` es además la **única** puerta de acceso a los archivos: la carpeta `pdf/`
no se enlaza nunca de forma directa. El script valida el identificador contra la base de
datos, fija `Content-Type: application/pdf` con `Content-Disposition: inline` y añade
`X-Content-Type-Options: nosniff`. `descargar.php` reutiliza ese mismo código cambiando
únicamente la disposición a `attachment`, con un nombre de archivo derivado del número de
oficio (`Oficio_0452_2026.pdf`) en lugar del nombre interno.

### 4.4 Respaldo versionado en GitHub

Además de guardarse en `pdf/`, cada documento digitalizado se envía al repositorio
mediante la **Contents API** de GitHub (`includes/github.php`). El archivo viaja
codificado en base64 dentro de un `PUT`, y si ya existía se recupera primero su `sha`
para reemplazarlo en lugar de duplicarlo. La URL resultante se guarda en la columna
`github_url` y se ofrece en el menú de acciones del documento.

Esto resuelve dos cosas a la vez:

1. Los PDF quedan **versionados** junto con el código, con historial de cambios.
2. En un despliegue sobre infraestructura con sistema de archivos efímero —como Vercel—
   los archivos subidos se perderían al reiniciarse la función. Por eso `ver_pdf.php`,
   cuando no encuentra el archivo local, lo **recupera automáticamente del respaldo**.

La integración se activa solo si existe la variable de entorno `GITHUB_TOKEN`. Si no está
definida, la aplicación funciona con normalidad usando únicamente el almacenamiento local:
la ausencia de la credencial degrada la funcionalidad, no la rompe.

### 4.5 Panel, reportes y administración

- **Inicio**: cuatro indicadores (total, recibidos, en proceso, atendidos), distribución
  porcentual por estatus, documentos de los últimos siete días, últimos registros y
  actividad reciente tomada de la bitácora.
- **Reportes**: documentos por mes de los últimos seis meses con promedio, porcentaje de
  atención, remitentes más frecuentes y **exportación a CSV**. Las gráficas están hechas
  con CSS puro, sin librerías de terceros.
- **Usuarios**: alta, activación/desactivación y baja, con roles diferenciados.
- **Configuración**: diagnóstico con semáforo de los tres componentes del sistema (base de
  datos, almacenamiento y respaldo remoto), datos del entorno de ejecución y conteo de
  registros por tabla. Sirve para verificar la instalación sin tocar el código.

---

## 5. Diseño responsivo

El desarrollo partió de la pantalla pequeña y fue creciendo hacia el escritorio.

| Franja              | Navegación                        | Listado de documentos               |
|---------------------|-----------------------------------|-------------------------------------|
| Móvil (< 768 px)    | Barra inferior + menú deslizable  | Tarjetas apiladas                   |
| Tableta (768–991 px)| Menú deslizable                   | Tarjetas apiladas                   |
| Escritorio (≥ 992 px)| Menú lateral fijo                | Tabla de siete columnas             |

La decisión de fondo es que una tabla de siete columnas resulta ilegible en un teléfono.
En lugar de dejar que se desborde horizontalmente o reducir la tipografía, por debajo de
992 px cada documento se dibuja como una **tarjeta** con el número de oficio y el estatus
en la parte superior, el asunto destacado y los datos restantes en pares etiqueta–valor.
Los botones de acción pasan de ser iconos pequeños a botones con área táctil suficiente.

La barra de navegación inferior reproduce el patrón de las aplicaciones móviles nativas,
con el botón flotante de “Nuevo” en el centro, que es la acción más frecuente.

---

## 6. Seguridad

| Riesgo                      | Medida aplicada                                                                                   |
|-----------------------------|---------------------------------------------------------------------------------------------------|
| Inyección SQL               | Sentencias preparadas con `bind_param` en toda consulta con datos del usuario. Donde no es posible parametrizar (nombre de columna, cláusula `ORDER BY`) se usa una **lista blanca**. |
| XSS                         | Toda la salida pasa por `htmlspecialchars` a través de la función `e()`.                            |
| CSRF                        | Token por sesión en cada formulario y en las peticiones AJAX, verificado con `hash_equals`.        |
| Subida de archivos maliciosos | Se comprueba el **tipo MIME real** con `finfo`, no la extensión declarada. Solo se aceptan `application/pdf` e `image/jpeg`, con un límite de 10 MB. |
| Nombres de archivo peligrosos | Se descarta el nombre original: se genera uno propio con fecha, cadena aleatoria y número de oficio normalizado. |
| Acceso directo a los archivos | La carpeta `pdf/` nunca se enlaza; todo pasa por `ver_pdf.php`, que valida el identificador.      |
| Inclusión de archivos        | El enrutador valida `?vista=` contra una lista blanca.                                             |
| Fuga de credenciales        | Las contraseñas y el token viven en variables de entorno; el repositorio no contiene ninguna.      |

En la pantalla de error de conexión tampoco se muestran usuario, host ni contraseña: solo
el nombre de la base esperada y la instrucción para importarla.

---

## 7. Pruebas realizadas

| #  | Caso de prueba                                                   | Entrada                                  | Resultado esperado                                  | Resultado |
|----|------------------------------------------------------------------|------------------------------------------|-----------------------------------------------------|-----------|
| 1  | Alta con PDF                                                     | Formulario completo + PDF de 2.6 MB      | Registro creado, archivo en `pdf/`, bitácora escrita | Correcto  |
| 2  | Alta con fotografía                                              | JPEG desde la cámara                     | PDF de una página generado y almacenado              | Correcto  |
| 3  | Validación de campos obligatorios                                | Formulario incompleto                    | Mensaje indicando los campos faltantes               | Correcto  |
| 4  | Validación de tipo de archivo                                    | Archivo que no es PDF ni JPEG            | Rechazo con mensaje explicativo                      | Correcto  |
| 5  | Visor en ventana modal                                           | Clic en el icono del ojo                 | PDF visible sobre la misma página                    | Correcto  |
| 6  | Descarga                                                         | Clic en descargar                        | `Content-Disposition: attachment`, nombre por oficio | Correcto  |
| 7  | Cambio de estatus por AJAX                                       | Recibido → Atendido                      | Etiqueta actualizada sin recarga, bitácora escrita   | Correcto  |
| 8  | Buscador y filtros                                               | Texto, estatus y rango de fechas         | Resultados acotados correctamente                    | Correcto  |
| 9  | Paginación y ordenamiento                                        | Varias páginas, cinco criterios          | Navegación coherente, filtros conservados            | Correcto  |
| 10 | Edición con y sin reemplazo de archivo                           | Cambio de datos                          | Actualización correcta, archivo anterior eliminado   | Correcto  |
| 11 | Eliminación                                                      | Confirmación en la ventana modal         | Registro, archivo y bitácora eliminados              | Correcto  |
| 12 | Exportación a CSV                                                | Botón en Reportes                        | Archivo con BOM UTF-8, acentos correctos en Excel    | Correcto  |
| 13 | Catálogo de usuarios                                             | Alta, duplicado, activar, eliminar       | Duplicado avisado sin romper la aplicación           | Correcto  |
| 14 | Protección CSRF                                                  | POST con token inválido                  | HTTP 403                                             | Correcto  |
| 15 | Identificador inexistente                                        | `ver_pdf.php?id=9999`                    | HTTP 404 con mensaje legible, sin exponer rutas      | Correcto  |
| 16 | Comportamiento responsivo                                        | 360 px, 768 px y 1440 px                 | Sin desplazamiento horizontal en ninguna franja      | Correcto  |

Las pruebas 1 a 15 se ejecutaron de forma automatizada contra el servidor local,
verificando los códigos de respuesta HTTP, el contenido devuelto y el estado resultante en
la base de datos.

---

## 8. Despliegue

El proyecto vive en el repositorio `mis-practicas-web` junto con las prácticas anteriores,
y se publica en Vercel. El archivo `vercel.json` de la raíz distingue las dos naturalezas
del repositorio: las prácticas 1 y 2 se sirven como páginas estáticas, mientras que la
práctica 4 se ejecuta con el runtime de PHP.

Como Vercel no provee un servidor MySQL, la base de datos se aloja en un proveedor externo
(Railway, Clever Cloud, Aiven o TiDB Cloud ofrecen MySQL gratuito) y sus credenciales se
capturan como variables de entorno en el panel de Vercel. Dado que el sistema de archivos
de Vercel es de solo lectura, el respaldo en GitHub descrito en el apartado 4.4 pasa a ser
el almacenamiento real de los PDF. El procedimiento completo está en el `README.md`.

---

## 9. Conclusiones

El objetivo del laboratorio se cumplió: la plataforma cubre los seis requerimientos
funcionales —registro, digitalización, estatus, búsqueda y filtros, visualización y
descarga, y responsividad— con las tecnologías solicitadas (HTML5, CSS3, jQuery, PHP y
MySQL) y sin recurrir a ningún framework.

Tres aspectos merecen destacarse por encima del cumplimiento literal:

**La digitalización desde el móvil es el punto donde la aplicación aporta más valor.**
Fotografiar un oficio y que el sistema lo convierta a PDF y lo registre en el mismo gesto
elimina el paso intermedio —escanear en una computadora— que en la práctica es lo que hace
que los documentos se queden sin digitalizar.

**El visor incrustado cambia la forma de trabajar con el listado.** Abrir el PDF en una
pestaña nueva obliga a perder el contexto de la búsqueda; abrirlo sobre la misma vista
permite revisar varios documentos en sucesión sin reconstruir los filtros cada vez.

**Separar presentación, consultas y acciones tuvo un costo inicial que se pagó pronto.**
El fragmento que dibuja la lista de documentos se escribió una vez y se usa en tres
pantallas distintas; al añadir el modo de tarjetas para móvil, las tres lo recibieron sin
tocar nada más.

Como líneas de mejora quedan la autenticación real con sesiones y contraseñas cifradas
—la tabla `usuarios` ya está preparada pero el acceso aún no se restringe—, la
notificación por correo al cambiar un estatus, y el reconocimiento óptico de caracteres
sobre las fotografías para poder buscar dentro del contenido del documento y no solo en
sus metadatos.
