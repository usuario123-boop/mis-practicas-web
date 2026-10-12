# Carpeta de documentos digitalizados

Aquí se guardan los archivos PDF que genera la aplicación al registrar un documento.

El nombre de cada archivo lo genera el servidor con el formato
`AAAAMMDD_HHMMSS_aleatorio_NoOficio.pdf`, para evitar colisiones y para no confiar
en el nombre que trae el archivo subido por el usuario.

Si está configurada la variable de entorno `GITHUB_TOKEN`, estos mismos archivos se
respaldan automáticamente en el repositorio a través de la API de GitHub.
