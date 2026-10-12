<?php
// Guarda las sesiones en MySQL. En Vercel cada petición puede atenderse en una
// instancia distinta, así que las sesiones en disco se pierden y con ellas el
// token CSRF y los mensajes flash.

require_once __DIR__ . '/../conexion.php';

final class SesionEnBaseDeDatos implements SessionHandlerInterface
{
    private mysqli $db;
    private int $vida;

    public function __construct(mysqli $db, int $vida)
    {
        $this->db   = $db;
        $this->vida = $vida;
    }

    public function open(string $ruta, string $nombre): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string
    {
        $stmt = $this->consultar(
            'SELECT datos FROM sesiones WHERE id = ? AND actualizada > ? LIMIT 1',
            'si',
            [$id, time() - $this->vida]
        );
        if ($stmt === null) return '';

        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $fila['datos'] ?? '';
    }

    public function write(string $id, string $datos): bool
    {
        $stmt = $this->consultar(
            'REPLACE INTO sesiones (id, datos, actualizada) VALUES (?, ?, ?)',
            'ssi',
            [$id, $datos, time()]
        );
        if ($stmt === null) return false;
        $stmt->close();
        return true;
    }

    public function destroy(string $id): bool
    {
        $stmt = $this->consultar('DELETE FROM sesiones WHERE id = ?', 's', [$id]);
        if ($stmt === null) return false;
        $stmt->close();
        return true;
    }

    public function gc(int $vida): int|false
    {
        $stmt = $this->consultar('DELETE FROM sesiones WHERE actualizada < ?', 'i', [time() - $vida]);
        if ($stmt === null) return false;
        $borradas = $this->db->affected_rows;
        $stmt->close();
        return max(0, $borradas);
    }

    // La tabla se crea sola la primera vez, para no depender de que se haya
    // importado el script actualizado en la base remota.
    private function consultar(string $sql, string $tipos, array $valores): ?mysqli_stmt
    {
        try {
            return $this->ejecutar($sql, $tipos, $valores);
        } catch (mysqli_sql_exception $e) {
            if ((int) $e->getCode() !== 1146) return null;
            try {
                $this->db->query(self::ESQUEMA);
                return $this->ejecutar($sql, $tipos, $valores);
            } catch (mysqli_sql_exception $otra) {
                return null;
            }
        }
    }

    private function ejecutar(string $sql, string $tipos, array $valores): mysqli_stmt
    {
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($tipos, ...$valores);
        $stmt->execute();
        return $stmt;
    }

    private const ESQUEMA = 'CREATE TABLE IF NOT EXISTS sesiones (
            id          VARCHAR(128) PRIMARY KEY,
            datos       MEDIUMBLOB   NOT NULL,
            actualizada INT UNSIGNED NOT NULL,
            INDEX idx_actualizada (actualizada)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
}

function iniciarSesion(mysqli $conexion): void
{
    if (session_status() !== PHP_SESSION_NONE) return;

    $vida = 2 * 60 * 60;

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if (ALMACEN_EFIMERO) {
        session_set_save_handler(new SesionEnBaseDeDatos($conexion, $vida), true);
        ini_set('session.gc_maxlifetime', (string) $vida);
        // La sesión debe escribirse antes de que PHP libere la conexión MySQL.
        register_shutdown_function('session_write_close');
    }

    session_start();
}
