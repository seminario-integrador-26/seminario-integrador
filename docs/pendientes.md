# Pendientes — continuar desde acá

Última sesión: 2026-09-16.

## Hecho en la última sesión

- UI: "Bitácora" pasó a llamarse **"Logs"** (menú, página `Usuarios/Auditoria`,
  enlace en `Usuarios/Index`). Siguen diciendo "bitácora" los comentarios del
  backend, el test `tests/Feature/Auditoria/BitacoraTest.php` y `diagrams/`.
- Tabla de usuarios (`Usuarios/Index.jsx`): la columna Rol muestra **solo los
  badges de los roles asignados** y ya no permite cambiarlos. Los roles se
  editan únicamente desde `Usuarios/Edit.jsx`.

## Decisiones pendientes / próximos pasos

1. **Proteger el rol "Administrador de sistema".** Hoy solo se impide que un
   admin se quite el rol **a sí mismo** (`UsuarioController::update()`). Un
   admin **sí** puede quitárselo a otro admin. Si la regla es "a nadie":
   - backend: en `update()` rechazar que se quite el rol a quien ya lo tiene;
   - `Usuarios/Edit.jsx`: deshabilitar ese checkbox (hoy está habilitado
     incluso para el propio usuario y el error aparece recién al guardar);
   - tests en `tests/Feature/Usuarios/`.
   // TODO: confirmar con el equipo la regla exacta.
2. **Ruta `PATCH usuarios/{usuario}/roles`** (`UsuarioController::cambiarRoles`)
   ya no la usa ninguna pantalla. Decidir si se elimina junto con sus tests
   (`GestionPermisosTest`).
3. **Bloqueo de cuenta (US-001)** — 3 intentos fallidos = 15 min
   (`BloqueoCuentaService`). No existe desbloqueo manual: ni el blanqueo de
   clave del admin ni el reset por email limpian `bloqueado_hasta`, y las
   sesiones abiertas siguen activas. Evaluar:
   - que el blanqueo de clave también desbloquee la cuenta;
   - un botón "Desbloquear" para el Administrador de sistema.
4. ESLint no corre: el proyecto no tiene archivo de configuración
   (`eslint.config.js`).
