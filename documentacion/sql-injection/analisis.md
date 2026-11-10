# Análisis técnico: SQL Injection

## 1. Descripción de la vulnerabilidad

SQL Injection es una vulnerabilidad que ocurre cuando una aplicación incorpora datos proporcionados por el usuario directamente en una consulta SQL sin separarlos correctamente de las instrucciones de la consulta.

Esto puede permitir que un atacante altere la lógica de la consulta y acceda a información que no debería obtener.

## 2. Identificación

- Aplicación: Damn Vulnerable Web Application (DVWA).
- Módulo: SQL Injection.
- Nivel de seguridad: Low.
- Archivo vulnerable: `vulnerabilities/sqli/source/low.php`.
- Tecnología: PHP y MySQL.

## 3. Explotación en el entorno de pruebas

Se introdujo el valor `1` en el campo de búsqueda, obteniendo el registro correspondiente al usuario administrador.

Posteriormente, se utilizó el siguiente payload:

`1' OR '1'='1`

La aplicación devolvió cinco registros de usuarios, demostrando que era posible alterar la condición de búsqueda mediante una inyección SQL.

**Evidencia:** versión vulnerable.

## 4. Mitigación implementada

Se modificó la consulta SQL para utilizar una sentencia preparada mediante las funciones de MySQLi:

- `mysqli_prepare()`
- `mysqli_stmt_bind_param()`
- `mysqli_stmt_execute()`
- `mysqli_stmt_get_result()`

El parámetro se vinculó como un entero (`i`), evitando que el contenido introducido se interprete como parte de la estructura SQL.

## 5. Validación

Se realizaron nuevamente dos pruebas:

1. Consulta normal con el valor `1`: devolvió el usuario administrador.
2. Intento de inyección con `1' OR '1'='1`: devolvió únicamente el registro correspondiente al ID 1.

Los resultados permiten comprobar que el payload utilizado ya no altera la lógica de la consulta en la ruta MySQL probada.

## 6. Evidencias

### 6.1. Versión vulnerable

**Consulta normal:**

![Consulta normal en la versión vulnerable](../../evidencias/sql-injection/vulnerable/01-consulta-normal.png)

**Explotación mediante SQL Injection:**

![Inyección SQL en la versión vulnerable](../../evidencias/sql-injection/vulnerable/02-inyeccion-vulnerable.png)

### 6.2. Versión mitigada

**Consulta normal después de la mitigación:**

![Consulta normal en la versión mitigada](../../evidencias/sql-injection/mitigada/01-consulta-normal.png)

**Intento de inyección después de la mitigación:**

![Intento de inyección SQL en la versión mitigada](../../evidencias/sql-injection/mitigada/02-inyeccion-mitigada.png)

## 7. Conclusión

La implementación de consultas preparadas permitió mitigar la vulnerabilidad SQL Injection en la rama MySQL del módulo analizado de DVWA. Las pruebas posteriores demostraron que la consulta normal continúa funcionando y que el payload utilizado durante la explotación ya no permite recuperar múltiples registros.

La mitigación descrita se aplica a la ruta MySQL probada. La rama SQLite del módulo no fue modificada ni validada.