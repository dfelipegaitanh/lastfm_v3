---
name: laravel
description: >-
  Use this skill whenever working on a Laravel application (backend routes, controllers,
  Eloquent models, migrations, FormRequests, background jobs, Artisan commands, Pest tests,
  Inertia/Livewire, and code styling with Pint). Also use for conducting rigorous, contextual
  code reviews and performance audits in Laravel codebases, enforcing deep dependency tracing
  before diagnosing bottlenecks.
---

# Laravel Senior Development & Code Review Skill

Este skill capacita al agente para actuar como un desarrollador senior y auditor de código en Laravel (especializado en Laravel 11+ / 12 / 13 con PHP 8.2+). Elimina diagnósticos superficiales, previene falsos positivos en revisiones de código y asegura la máxima calidad arquitectónica.

---

## 1. Protocolo Anti-Brutalidad: Revisión de Código Contextual ("Trace Before Judging")

> [!CRITICAL]
> **REGLA SUPREMA DE REVISIÓN DE CÓDIGO:**
> Está estrictamente prohibido juzgar un método, comando o controlador viendo un solo archivo de forma aislada. Todo diagnóstico debe seguir el protocolo de rastreo antes de emitir cualquier juicio o porcentaje.

### El Protocolo de 4 Pasos Obligatorio:
1. **Rastreo de Fachadas y Clientes (No Asumir Comportamiento Externo):**
   - Si se observa una llamada a un Facade o servicio externo dentro de un bucle (ej: `LastFm::getTrackInfo()`, `Spotify::search()`, `PaymentGateway::charge()`), es **OBLIGATORIO** abrir e inspeccionar la clase subyacente (`app/Clients/`, `app/Services/`).
   - **Verificar Capas de Caché:** Comprobar si el cliente implementa `Cache::rememberForever()`, `Cache::remember()`, memoización estática o un store en Redis/Memcached. Si la llamada está cacheada, **NO es un cuello de botella de red N+1** para datos recurrentes.
2. **Reconocimiento de Patrones de Ingesta Resiliente (Checkpointing vs Transacciones):**
   - Ante sincronizaciones masivas con APIs de terceros, evaluar si el autor utilizó deliberadamente **consistencia eventual o reanudación por puntos de control (checkpointing)**.
   - Si una transacción estricta (`DB::transaction`) causaría que un fallo en el registro 49 revierta y descarte 48 registros ya obtenidos con éxito de la API externa, la persistencia progresiva **NO se califica como defecto**, sino como una estrategia deliberada de tolerancia a fallos.
3. **Inspección de Mapas en Memoria y Operaciones Masivas:**
   - Verificar si la clase mantiene mapas asociativos privados (ej. `$artistIdMap`, `$trackIdMap`) para resolver llaves foráneas en RAM.
   - Verificar si el guardado final se realiza mediante `upsert()` o `insert()` masivo en lugar de inserts individuales.
4. **Verificación de Telemetría Real:**
   - Si el comando utiliza `DB::listen` o métricas de conteo de llamadas (ej. `getCallLog()`), guiarse por los datos medidos reales y no por suposiciones teóricas.

---

## 2. Flujo de Trabajo Operativo (Desarrollo y Modificación)

```
[1. Inspeccionar Dependencias] ➔ [2. Escribir Código Tipado] ➔ [3. Validar Pest] ➔ [4. Formatear Pint]
```

### Reglas Técnicas Innegociables:
* **Estructura Moderna (Laravel 11+):**
  - No buscar ni generar `app/Http/Kernel.php` ni `app/Console/Kernel.php`. 
  - Todo middleware, excepción y prefijo se declara en `bootstrap/app.php`.
  - Las tareas programadas se declaran en `routes/console.php` con la fachada `Schedule`.
* **Eloquent y Modelado:**
  - Los casts deben declararse obligatoriamente con el método protegido `protected function casts(): array` (nunca la propiedad protegida `$casts`).
  - Prevenir N+1 en desarrollo respetando `Model::shouldBeStrict(!app()->isProduction())`.
* **Capa HTTP:**
  - Controladores siempre delgados. Lógica de validación compleja encapsulada en clases `FormRequest`.
  - Respuestas JSON mediante `JsonResource` para evitar fugas de atributos ocultos.
* **Calidad y Estilo:**
  - Todo cambio con lógica de negocio debe acompañarse de su prueba en **Pest** utilizando Fakes (`Http::fake()`, `Queue::fake()`).
  - Antes de concluir cualquier tarea, ejecutar: `./vendor/bin/pint --dirty`.

---

## 3. Módulos de Referencia Especializada (Progressive Disclosure)

Consulta estos archivos bajo demanda según la naturaleza de la tarea:

* **[Protocolo de Revisión y Auditoría](./references/04-code-review-protocol.md):** Guía exhaustiva para calificar código, detectar problemas reales y evitar falsos positivos.
* **[Arquitectura, Service Container & HTTP](./references/01-architecture-core-http.md):** Ciclo de vida, bindings del contenedor, middleware en `bootstrap/app.php` y FormRequests.
* **[Eloquent, Migraciones & Base de Datos](./references/02-eloquent-database.md):** Transacciones con reintentos de deadlocks, upserts masivos, método `casts()` y prevención de N+1.
* **[Asincronía, Seguridad & Testing](./references/03-async-security-testing.md):** Jobs con `ShouldQueue` y `ShouldBeUnique`, tareas programadas, fachadas Concurrency/Context y tests en Pest con Fakes.

---

## 4. Scripts de Diagnóstico Integrados

* `bash .agents/skills/laravel/scripts/verify-health.sh`: Ejecuta en secuencia Pint, Pest, validación de rutas y chequeo de configuración.
* `bash .agents/skills/laravel/scripts/safe-artisan.sh <comando>`: Envoltorio no interactivo que bloquea comandos destructivos en producción.
