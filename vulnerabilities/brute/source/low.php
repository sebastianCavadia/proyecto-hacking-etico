
<?php

if (isset($_GET['Login'])) {

    // Configuracion de seguridad
    $max_failed_attempts = 3;
    $lockout_seconds = 15 * 60;

    // Obtener los datos del formulario
    $user = isset($_GET['username']) ? (string) $_GET['username'] : '';
    $pass = isset($_GET['password']) ? (string) $_GET['password'] : '';

    $db = $GLOBALS["___mysqli_ston"];

    // Mensaje generico para evitar revelar informacion de las cuentas
    $error_message = '<pre><br />Username and/or password incorrect.</pre>';

    if ($user !== '' && $pass !== '' && $db) {

        // Buscar el usuario mediante una consulta preparada
        $stmt = mysqli_prepare(
            $db,
            'SELECT password, avatar, failed_login, account_enabled,
             TIMESTAMPDIFF(SECOND, last_login, NOW())
             FROM users WHERE user = ? LIMIT 1;'
        );

        if ($stmt) {

            mysqli_stmt_bind_param($stmt, 's', $user);
            mysqli_stmt_execute($stmt);

            mysqli_stmt_bind_result(
                $stmt,
                $stored_password,
                $avatar,
                $failed_login,
                $account_enabled,
                $seconds_since_last_login
            );

            $user_found = mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);

            if ($user_found) {

                $failed_login = (int) $failed_login;

                // Comprobar si la cuenta esta bloqueada
                $account_locked = (
                    $failed_login >= $max_failed_attempts &&
                    $seconds_since_last_login !== null &&
                    (int) $seconds_since_last_login < $lockout_seconds
                );

                if ($account_enabled != 1 || $account_locked) {

                    $html .= $error_message;

                } else {

                    // Si el bloqueo ya expiro, reiniciar el contador
                    if ($failed_login >= $max_failed_attempts) {
                        $reset = mysqli_prepare(
                            $db,
                            'UPDATE users SET failed_login = 0 WHERE user = ? LIMIT 1;'
                        );

                        if ($reset) {
                            mysqli_stmt_bind_param($reset, 's', $user);
                            mysqli_stmt_execute($reset);
                            mysqli_stmt_close($reset);
                        }

                        $failed_login = 0;
                    }

                    // Comprobar la contrasena
                    if (hash_equals((string) $stored_password, md5($pass))) {

                        // Inicio de sesion correcto: reiniciar contador
                        $update = mysqli_prepare(
                            $db,
                            'UPDATE users SET failed_login = 0,
                             last_login = NOW() WHERE user = ? LIMIT 1;'
                        );

                        if ($update) {
                            mysqli_stmt_bind_param($update, 's', $user);
                            mysqli_stmt_execute($update);
                            mysqli_stmt_close($update);
                        }

                        $safe_user = htmlspecialchars($user, ENT_QUOTES, 'UTF-8');
                        $safe_avatar = htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8');

                        $html .= "<p>Welcome to the password protected area <em>{$safe_user}</em></p>";
                        $html .= "<img src=\"{$safe_avatar}\" />";

                    } else {

                        // Contrasena incorrecta: incrementar contador
                        $update = mysqli_prepare(
                            $db,
                            'UPDATE users SET failed_login = failed_login + 1,
                             last_login = NOW() WHERE user = ? LIMIT 1;'
                        );

                        if ($update) {
                            mysqli_stmt_bind_param($update, 's', $user);
                            mysqli_stmt_execute($update);
                            mysqli_stmt_close($update);
                        }

                        $html .= $error_message;
                    }
                }

            } else {

                // Usuario inexistente: mostrar el mismo mensaje generico
                $html .= $error_message;
            }

        } else {

            $html .= $error_message;
        }

    } else {

        $html .= $error_message;
    }

    mysqli_close($db);
}

?>
