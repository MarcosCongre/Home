# T2 - Suite automatizada del backend

Fecha de ejecución: 2026-09-23 (hora local observada)

## Intentos

| Comando | Resultado | Duración |
|---|---|---:|
| `composer test` desde la raíz del workspace | No válido: se ejecutó fuera de `home-backend` y no encontró el script | 0.680 s |
| `composer test` desde `home-backend` | Bloqueado por falta de `openssl` en PHP CLI | 0.430 s |
| `php vendor/bin/phpunit --testdox` desde `home-backend` | Bloqueado por requisito de PHP `>= 8.3` | 0.143 s |

## Evidencia del bloqueo

- PHP CLI activo: `8.2.33`.
- `php --ini` carga `C:\php82\php.ini`.
- `php -m` no muestra la extensión `openssl`.
- Composer informa: `The openssl extension is required for SSL/TLS protection but is not available`.
- PHPUnit directo informa que las dependencias instaladas requieren PHP `>= 8.3.0`.
- `home-backend/composer.json` declara `php: ^8.2`, pero el `vendor/` instalado no es ejecutable con el PHP CLI disponible.
- La suite no llegó a descubrir ni ejecutar casos PHPUnit.

## Clasificación

**T2 bloqueada por entorno; resultado de tests no determinable.** No se clasifican fallos funcionales porque PHPUnit no pudo iniciar. No se modificaron código, dependencias ni datos de la base.

## Acción requerida antes de repetir T2

Usar un PHP compatible con las dependencias instaladas, preferiblemente PHP 8.3 o superior, y habilitar `openssl`. Después ejecutar desde `home-backend`:

```powershell
composer test
```

La suite solo podrá marcarse como pasada cuando Composer y PHPUnit completen la ejecución y se registren sus contadores de tests, errores y fallos.