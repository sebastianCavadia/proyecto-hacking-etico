# Análisis y mitigación de vulnerabilidad Brute Force en DVWA

## 1. Información general

**Aplicación:** Damn Vulnerable Web Application (DVWA)  
**Vulnerabilidad:** Brute Force (fuerza bruta)  
**Nivel de seguridad analizado:** Low  
**Archivo modificado:** `vulnerabilities/brute/source/low.php`  
**Entorno:** Laboratorio local mediante Docker  
**Tipo de intervención:** Mitigación de intentos repetidos de autenticación.

### Clasificación

- **CWE-307:** Improper Restriction of Excessive Authentication Attempts (restricción inadecuada de intentos excesivos de autenticación).
- **OWASP Top 10:** A07:2025 – Authentication Failures.

---

## 2. Descripción de la vulnerabilidad

Un ataque de fuerza bruta consiste en intentar diferentes combinaciones de contraseñas hasta encontrar una que permita acceder a una cuenta.

Este tipo de ataque puede tener éxito cuando una aplicación permite realizar numerosos intentos de autenticación sin establecer restricciones suficientes.

En el nivel Low de DVWA, el formulario de inicio de sesión no implementaba un mecanismo para limitar los intentos fallidos. Por lo tanto, era posible probar repetidamente diferentes contraseñas contra una misma cuenta.

### 2.1. Funcionamiento del código vulnerable

El código original obtenía el nombre de usuario y la contraseña mediante parámetros GET:

```php
$user = $_GET['username'];
$pass = $_GET['password'];
$pass = md5($pass);

$query = "SELECT * FROM `users`
          WHERE user = '$user'
          AND password = '$pass';";

$result = mysqli_query(
    $GLOBALS["___mysqli_ston"],
    $query
);
```

Después, verificaba si la consulta había encontrado un usuario.

El problema principal relacionado con fuerza bruta era que no existía un contador de intentos fallidos, un período de bloqueo ni un mecanismo de limitación de intentos.

Además, el código original concatenaba directamente los datos recibidos en una consulta SQL, lo que también representaba un riesgo independiente de inyección SQL.

---

## 3. Objetivos de la mitigación

La modificación busca implementar las siguientes medidas:

1. Limitar a tres los intentos fallidos de autenticación.
2. Bloquear temporalmente una cuenta al alcanzar el límite.
3. Establecer un período de bloqueo de 15 minutos.
4. Permitir nuevamente los intentos cuando finalice el bloqueo.
5. Reiniciar el contador después de una autenticación exitosa.
6. Utilizar consultas preparadas para interactuar con la base de datos.
7. Mostrar mensajes genéricos para evitar revelar información sobre las cuentas.

---

## 4. Implementación de la mitigación

Se modificó el archivo:

`vulnerabilities/brute/source/low.php`

### 4.1. Configuración del límite de intentos

Se establecieron dos parámetros:

```php
$max_failed_attempts = 3;
$lockout_seconds = 15 * 60;
```

El primero establece el máximo de intentos fallidos permitidos.

El segundo define un período de bloqueo de 900 segundos, equivalente a 15 minutos.

### 4.2. Consulta preparada

Para buscar al usuario se implementó una consulta preparada:

```php
$stmt = mysqli_prepare(
    $db,
    'SELECT password, avatar, failed_login,
     account_enabled,
     TIMESTAMPDIFF(SECOND, last_login, NOW())
     FROM users
     WHERE user = ?
     LIMIT 1;'
);
```

El parámetro se vincula mediante:

```php
mysqli_stmt_bind_param($stmt, 's', $user);
```

Esto evita concatenar directamente el nombre de usuario dentro de la consulta SQL.

### 4.3. Comprobación del bloqueo

Se verifica si el usuario alcanzó el límite de intentos y si todavía se encuentra dentro del período de bloqueo:

```php
$account_locked = (
    $failed_login >= $max_failed_attempts &&
    $seconds_since_last_login !== null &&
    (int) $seconds_since_last_login < $lockout_seconds
);
```

Si la cuenta está deshabilitada o bloqueada, el sistema rechaza el inicio de sesión.

### 4.4. Reinicio después del vencimiento

Cuando han transcurrido los 15 minutos, el contador se reinicia:

```php
if ($failed_login >= $max_failed_attempts) {
    $reset = mysqli_prepare(
        $db,
        'UPDATE users
         SET failed_login = 0
         WHERE user = ?
         LIMIT 1;'
    );
}
```

De esta manera, la cuenta puede volver a realizar intentos de autenticación.

### 4.5. Autenticación exitosa

Cuando las credenciales son correctas, se actualizan los campos correspondientes:

```php
UPDATE users
SET failed_login = 0,
    last_login = NOW()
WHERE user = ?
LIMIT 1;
```

Esto permite reiniciar el contador después de un inicio de sesión válido.

### 4.6. Autenticación fallida

Cuando la contraseña no coincide, se incrementa el contador:

```php
UPDATE users
SET failed_login = failed_login + 1,
    last_login = NOW()
WHERE user = ?
LIMIT 1;
```

Al alcanzar tres intentos fallidos, los siguientes intentos quedan bloqueados durante el período establecido.

### 4.7. Mensajes de error genéricos

Se utiliza el mismo mensaje para los casos de autenticación fallida:

```php
$error_message =
    '<pre><br />Username and/or password incorrect.</pre>';
```

Esto evita informar directamente si una cuenta existe, está deshabilitada o se encuentra bloqueada.

---

## 5. Pruebas y validación

Las pruebas se realizaron exclusivamente en el entorno local de DVWA.

Se utilizó la cuenta de prueba `admin` y se consultó la tabla `users` de MariaDB para verificar los cambios en los campos `failed_login` y `last_login`.

### 5.1. Registro de intentos fallidos

Se realizaron tres intentos de autenticación con una contraseña incorrecta.

El resultado obtenido en la base de datos fue:

| Usuario | Intentos fallidos |
|---|---:|
| admin | 3 |

Esto confirmó que el sistema incrementaba correctamente el contador.

### 5.2. Comprobación del bloqueo

Después del tercer intento fallido se realizó un cuarto intento.

La aplicación rechazó el acceso y el contador permaneció en tres.

También se comprobó que `last_login` no cambiara durante los intentos rechazados por el bloqueo.

### 5.3. Comprobación del vencimiento

Para no esperar los 15 minutos reales, se modificó temporalmente el campo `last_login` desde MariaDB:

```sql
UPDATE users
SET last_login = NOW() - INTERVAL 16 MINUTE
WHERE user = 'admin';
```

Esto permitió simular que el período de bloqueo había finalizado.

Después se realizó otro intento con una contraseña incorrecta.

El resultado fue:

| Usuario | Intentos fallidos |
|---|---:|
| admin | 1 |

Esto confirmó que el contador se reinició y que el nuevo intento fue procesado.

### 5.4. Comprobación de autenticación exitosa

Finalmente, se inició sesión utilizando las credenciales válidas de la cuenta de prueba.

La consulta realizada fue:

```sql
SELECT user, failed_login, last_login
FROM users
WHERE user = 'admin';
```

Resultado obtenido:

| Usuario | Intentos fallidos |
|---|---:|
| admin | 0 |

El campo `last_login` también se actualizó, confirmando que el contador se reinicia después de una autenticación exitosa.

---

## 6. Resumen de resultados

| Prueba | Resultado esperado | Resultado obtenido |
|---|---|---|
| Tres intentos incorrectos | Contador llega a 3 | Correcto |
| Cuarto intento durante el bloqueo | Acceso rechazado | Correcto |
| Intento durante el bloqueo | Contador permanece en 3 | Correcto |
| Intento después de 16 minutos | Contador reiniciado y nuevo intento permitido | Correcto |
| Inicio de sesión válido | Contador vuelve a 0 | Correcto |

**Resultado general:** Las pruebas realizadas confirmaron el funcionamiento de los mecanismos de bloqueo temporal, vencimiento y reinicio del contador.

---

## 7. Limitaciones y recomendaciones

Aunque la modificación mitiga el problema de intentos ilimitados en el nivel Low de DVWA, existen aspectos que deberían mejorarse en un sistema real.

### 7.1. Uso de MD5

La aplicación conserva MD5 para verificar las contraseñas porque las cuentas de prueba de DVWA utilizan ese formato.

MD5 no es adecuado para almacenar contraseñas en sistemas reales.

Se recomienda utilizar funciones especializadas como:

- `password_hash()` para generar hashes.
- `password_verify()` para verificar contraseñas.

### 7.2. Bloqueo por cuenta

El bloqueo se aplica a una cuenta específica. Un atacante podría provocar que una cuenta legítima quede bloqueada intencionalmente.

Se recomienda complementar esta medida con limitación de solicitudes por dirección IP, controles de riesgo y mecanismos de autenticación multifactor.

### 7.3. Concurrencia

La implementación actual realiza la comprobación del contador y su actualización mediante operaciones separadas.

En un sistema con múltiples solicitudes simultáneas, sería recomendable reforzar estas operaciones mediante transacciones o actualizaciones atómicas.

### 7.4. Registro y monitoreo

Un sistema real debería registrar los intentos fallidos, detectar patrones anormales y generar alertas cuando se presenten múltiples intentos de autenticación sospechosos.

### 7.5. Almacenamiento de archivos y ejecución

La mitigación implementada corresponde al nivel Low de DVWA y constituye una práctica educativa. No debe considerarse una solución de autenticación lista para producción.

---

## 8. Conclusión

La vulnerabilidad de fuerza bruta presente en el nivel Low de DVWA permitía realizar intentos repetidos de autenticación sin restricciones.

Para mitigar este comportamiento se implementó un contador de intentos fallidos, un límite de tres intentos y un bloqueo temporal de 15 minutos.

Las pruebas realizadas permitieron comprobar que el sistema bloquea nuevos intentos al alcanzar el límite, restablece el contador después del vencimiento del bloqueo y lo reinicia cuando el usuario inicia sesión correctamente.

Adicionalmente, se incorporaron consultas preparadas para reducir el riesgo de inyección SQL en el proceso de autenticación.

La implementación demuestra cómo los controles de autenticación y la limitación de intentos pueden reducir la exposición de una aplicación frente a ataques de fuerza bruta en un entorno controlado.

**Estado de la mitigación:** Implementada y validada mediante pruebas locales.