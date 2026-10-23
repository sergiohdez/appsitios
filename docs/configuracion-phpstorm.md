# Configurar PhpStorm para desarrollo local

Esta guía configura el intérprete PHP del proyecto y una configuración reutilizable de Laravel para ejecutar `php artisan serve`. Los pasos aplican también a IntelliJ IDEA con el complemento PHP instalado.

## 1. Configurar PHP y Composer

1. Abre la carpeta raíz del repositorio en PhpStorm.
2. Ve a **Settings | PHP** y agrega el intérprete CLI que usa Composer y Laravel. Comprueba con `php -v` que sea PHP 8.3 o posterior.
3. En **Settings | PHP | Composer**, selecciona el `composer.json` del proyecto y configura la ruta del ejecutable de Composer si el IDE no la detecta.
4. Abre una terminal en la raíz del proyecto y ejecuta `composer install` si todavía no existe `vendor/`.
5. Confirma que `.env` exista y tenga la conexión local a la base de datos configurada. No guardes ese archivo en Git.

## 2. Run configuration: servidor de Laravel

1. Selecciona **Run | Edit Configurations… | + | PHP Script**.
2. Ponle un nombre, por ejemplo **Laravel: artisan serve**.
3. Completa los campos así:

   | Campo | Valor |
   | --- | --- |
   | File | `$ProjectFileDir$/artisan` |
   | Arguments | `serve --host=127.0.0.1 --port=8000` |
   | Working directory | `$ProjectFileDir$` |
   | PHP interpreter | El intérprete CLI configurado para el proyecto |

4. Deja **Environment variables** vacío salvo que necesites sobreescribir una variable de `.env` para esa ejecución. Laravel carga `.env` desde el directorio del proyecto.
5. Guarda y pulsa **Run**. La consola del IDE debe mostrar que el servidor escucha en `http://127.0.0.1:8000`; abre esa dirección en el navegador. Usa **Stop** para apagarlo.

`artisan serve` permanece activo en la ventana de ejecución del IDE. No crees una configuración de servidor web PHP separada para este flujo: esta configuración invoca directamente el servidor de desarrollo de Laravel.

## 3. Debug de solicitudes web con Xdebug

PhpStorm necesita Xdebug instalado y habilitado para el mismo PHP CLI que usa la configuración anterior.

1. Comprueba el `php.ini` cargado por ese intérprete con `php --ini` e instala una versión de Xdebug compatible con la versión, arquitectura y modalidad de ese PHP.
2. En el `php.ini` CLI, configura Xdebug 3. Ajusta la ruta de `zend_extension` a la DLL instalada:

   ```ini
   zend_extension="C:\ruta\a\php_xdebug.dll"
   xdebug.mode=debug,develop
   xdebug.start_with_request=trigger
   xdebug.client_host=127.0.0.1
   xdebug.client_port=9003
   ```

3. Reinicia PhpStorm si cambiaste el `php.ini`. Ve a **Settings | PHP | Debug** y fija el puerto Xdebug en `9003`.
4. En PhpStorm, activa **Start Listening for PHP Debug Connections** (icono del teléfono). Pon un breakpoint en el controlador o código que quieres inspeccionar.
5. Inicia **Laravel: artisan serve** con **Run** y visita la ruta que activa el código. Envía el disparador `XDEBUG_TRIGGER=1` desde una extensión de navegador compatible o como cookie para esa petición.
6. PhpStorm debe detenerse en el breakpoint. Si no lo hace, verifica que `php -v` muestra Xdebug, que modificaste el `php.ini` correcto con `php --ini`, que PhpStorm escucha en el mismo puerto y que la petición lleva el disparador.

El comando Artisan corre en un proceso CLI y las solicitudes web las atiende el proceso worker que inicia `artisan serve`; Xdebug debe estar activo para ese mismo intérprete CLI.

## 4. Debug de comandos Artisan y pruebas

Para depurar una migración, seeder u otro comando Artisan, crea otra configuración **PHP Script** con `File` en `$ProjectFileDir$/artisan`, el nombre del comando en `Arguments` (por ejemplo `migrate:status`) y el directorio de trabajo en `$ProjectFileDir$`. Inicia la escucha de Xdebug y pulsa **Debug**.

También puedes depurar la suite con `Arguments` igual a `test`. Para ejecutar PHPUnit desde el panel del IDE, configura **Settings | PHP | Test Frameworks | PHPUnit** para usar el autoloader `vendor/autoload.php` del proyecto y `phpunit.xml` como configuración.
