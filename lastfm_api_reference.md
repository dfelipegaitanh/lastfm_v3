# Referencia Técnica y Reglas de la API de Last.fm (v2.0)

Documentación técnica completa extraída de `https://www.last.fm/api/intro`, guías oficiales de transporte REST, especificación de Autenticación 1.0, Scrobbling 2.0, Términos de Servicio y catálogo de métodos de los paquetes **`auth.*`**, **`album.*`**, **`artist.*`**, **`tag.*`**, **`track.*`** y **`user.*`** *(excluyendo `chart.*`, `geo.*`, `library.*` y `user.getFriends`)*.

---

## 1. Arquitectura General, Transporte y Reglas de Uso

### 1.1 Endpoint Raíz (API Root)
Todas las peticiones (tanto de lectura como de escritura) se envían al mismo endpoint raíz:
```text
http://ws.audioscrobbler.com/2.0/
```
*(También disponible bajo `https://ws.audioscrobbler.com/2.0/`, siendo **HTTPS obligatorio** para `auth.getMobileSession`).*

### 1.2 Parámetros Globales de Transporte
En cualquier llamada a la API se deben enviar los siguientes parámetros base junto con los argumentos específicos de cada método:

- **`method`** *(Requerido)*: Nombre del método expresado en formato `package.method` (ej. `album.getInfo`, `track.getInfo`, `user.getWeeklyTrackChart`).
- **`api_key`** *(Requerido)*: Clave de aplicación de Last.fm de 32 caracteres.
- **`format`** *(Opcional)*: Por defecto la API responde en **XML** nativo de Last.fm. Para recibir la respuesta en **JSON**, se debe enviar `format=json`.
- **`callback`** *(Opcional, solo con `format=json`)*: Nombre de la función envolvente para respuestas JSONP.
  - Sin `callback`: devuelve JSON puro con MIME type `application/json`.
  - Con `callback`: devuelve JSONP con MIME type `text/javascript`.
- **`raw`** *(Opcional)*: Enviar `raw=true` elimina el nodo envolvente `<lfm status="ok">` de la respuesta.

### 1.3 Verbos HTTP y Codificación
- **Servicios de Lectura (Read Services):**
  - Se consultan mediante peticiones **`HTTP GET`** pasando los parámetros en el query string.
  - El cliente HTTP debe cumplir con **RFC 3986** (prestar especial atención al URL encoding de nombres de artistas, álbumes y canciones).
- **Servicios de Escritura (Write Services):**
  - Se ejecutan obligatoriamente mediante peticiones **`HTTP POST`** dirigidas a la URL raíz (`http://ws.audioscrobbler.com/2.0/`).
  - **Todos** los parámetros (incluyendo `method`, `api_key`, `sk` y `api_sig`) deben enviarse en el cuerpo de la petición (`POST body`) codificados como `application/x-www-form-urlencoded`.
  - Todos los servicios de escritura requieren **autenticación**.
- **Codificación de Caracteres (Encoding):**
  - Todos los argumentos enviados a los métodos de la API deben ir codificados en **UTF-8**.
- **Transporte Alternativo XML-RPC (`/api/xmlrpc`):**
  - Se envían peticiones `HTTP POST` a `http://ws.audioscrobbler.com/2.0/` con un cuerpo XML `<methodCall><methodName>package.method</methodName><params><param><value><struct>...</struct></value></param></params></methodCall>`, pasando cada parámetro como un `<member>` con `<name>` y `<value><string>...</string></value>`.
- **Servicios Deprecados:**
  - **Radio API (`/api/radio`)** y **Playlists API (`/api/playlists`)** están oficialmente deprecados y ya no tienen soporte activo.

### 1.4 Reglas Operativas, Rate Limiting y Condiciones de Uso (`/api/intro` y `/api/tos`)
1. **Header `User-Agent` Obligatorio:** Todas las peticiones deben incluir un encabezado `User-Agent` único e identificable de la aplicación. Esto facilita el registro en Last.fm y reduce el riesgo de bloqueo de IP o baneo de la cuenta API.
2. **Frecuencia de Peticiones y Caché:**
   - Prohibido realizar múltiples llamadas continuas por segundo o disparar llamadas a la API directamente en cada carga de página (`page load`) de una aplicación web. Hacer llamadas excesivas puede resultar en la suspensión de la cuenta API.
   - Se debe implementar caché local respetando las cabeceras HTTP de las respuestas.
3. **Límite de Almacenamiento (*Reasonable Usage Cap*):**
   - La licencia estándar limita el almacenamiento total de datos de Last.fm (*Last.fm Data*) a un máximo de **100 MB** en cualquier momento. Superar este límite requiere autorización escrita previa (`partners@last.fm`).
4. **Uso No Comercial y Reglas de Atribución:**
   - El uso estándar de la API es estrictamente **no comercial** (el uso comercial o académico/investigación requiere acuerdo previo vía `partners@last.fm`).
   - Se debe acreditar a Last.fm y enlazar cada entidad mostrada hacia su URL oficial en Last.fm:
     - Perfil de usuario: `http://www.last.fm/user/<username>`
     - Artista: `http://www.last.fm/music/<artistname>`
     - Álbum: `http://www.last.fm/music/<artistname>/<albumname>`
     - Canción: `http://www.last.fm/music/<artistname>/_/<trackname>`
   - La API no otorga derechos para hacer streaming de audio/video ni usar portadas/imágenes fuera de los términos permitidos.

---

## 2. Cómo Devuelve la Información la API (XML y Reglas de Conversión a JSON)

### 2.1 Estructura XML por Defecto
En XML, todas las respuestas exitosas vienen envueltas en un nodo raíz `<lfm status="ok">`:
```xml
<?xml version="1.0" encoding="utf-8"?>
<lfm status="ok">
    <!-- Nodo raíz específico del método, ej. <album>, <track>, <user>, <weeklytrackchart> -->
</lfm>
```

### 2.2 Reglas Oficiales de Traducción XML a JSON (`format=json`)
La respuesta JSON es una traducción directa del documento XML aplicando 4 reglas de transformación:

1. **Atributos XML:** Se expresan como propiedades de tipo `string` usando el nombre del atributo como clave. En contenedores de listas, paginación o elementos con metadatos de posición, Last.fm agrupa los atributos bajo la clave **`"@attr"`** (ej. `"@attr": { "rank": "1" }` o `"@attr": { "user": "RJ", "page": "1", "perPage": "50", "totalPages": "10", "total": "500" }`).
2. **Nodos hijos (Elementos):** Se expresan como miembros de un objeto JSON usando el nombre del nodo XML como clave.
3. **Nodos hijos de texto (`#text`):**
   - Si un elemento XML **solo** contiene texto (ej. `<name>Believe</name>`), en JSON se convierte en un `string` directo: `"name": "Believe"`.
   - Si un elemento XML **contiene texto Y además tiene atributos** (ej. `<image size="large">http://...</image>`, `<date uts="1213031819">9 Jun 2008, 17:16</date>`, o `<artist mbid="17b0...">Dirk Leyers</artist>`), el texto interno se asigna a la clave **`"#text"`** y los atributos quedan como claves hermanas:
     ```json
     {
       "mbid": "17b0d7f1-fad3-404e-87ae-874e6e158c3a",
       "#text": "Dirk Leyers"
     }
     ```
4. **Nodos hijos repetidos (Listas):** Los nodos hermanos con el mismo nombre (ej. múltiples `<track>`, `<album>`, `<artist>`, `<tag>`, `<image>`, `<chart>`) se agrupan en un `array` JSON bajo el nombre compartido del nodo.
   - *Detalle clave de implementación:* Todos los valores escalares (incluyendo conteos numéricos como `playcount`, `listeners`, `duration`, `uts`, `from`, `to`, `rank`) se serializan como **`string`** en JSON debido a la conversión directa desde XML.

---

## 3. Autenticación y Firma de Peticiones (`auth.*`)

Todos los métodos de escritura requieren autenticación de usuario, y algunos métodos de lectura cambian su comportamiento o toman al usuario autenticado por defecto si se autentican.

### 3.1 Requisitos de Cuenta API
Cada cuenta en Last.fm dispone de:
- **`api_key`** (32 caracteres): Identificador público de la aplicación.
- **`secret`** (32 caracteres): Secreto privado usado exclusivamente para firmar peticiones (`api_sig`).

### 3.2 Los 3 Flujos de Autenticación

#### A. Flujo para Aplicaciones Web (`/api/webauth`)
1. **Solicitar autorización al usuario:** Redirigir al navegador mediante `HTTP GET` a:
   ```text
   http://www.last.fm/api/auth/?api_key=xxxxxxxxxx
   ```
   *(Se puede adjuntar `&cb=https://tu-dominio.com/callback` o configurar el Callback URL en el panel de la cuenta API).*
2. **Recibir el Token:** Una vez el usuario aprueba el acceso en Last.fm, es redirigido al Callback URL con el parámetro `token` por GET:
   ```text
   <callback_url>/?token=yyyyyy
   ```
3. **Crear Sesión (`auth.getSession`):** Llamar al método `auth.getSession` enviando `api_key`, `token` y `api_sig`. Devuelve la clave de sesión (`sk`).

#### B. Flujo para Aplicaciones de Escritorio (`/api/desktopauth`)
1. **Obtener un token no autorizado (`auth.getToken`):** Llamar a `auth.getToken` enviando `api_key` y `api_sig`. Devuelve un `token`.
2. **Autorizar el token en navegador:** Abrir el navegador del usuario en:
   ```text
   http://www.last.fm/api/auth/?api_key=xxxxxxxxxx&token=yyyyyy
   ```
3. **Crear Sesión (`auth.getSession`):** Una vez el usuario autoriza en el navegador, llamar a `auth.getSession` enviando `api_key`, `token` y `api_sig` para obtener `sk`.

#### C. Flujo para Aplicaciones Móviles (`/api/mobileauth`)
1. **Llamada directa por HTTPS POST (`auth.getMobileSession`):** Enviar `username` (nombre de usuario o email), `password` (en texto plano), `api_key` y `api_sig` mediante **`HTTPS POST`** a `auth.getMobileSession`. Devuelve directamente el objeto `session` con `key` (`sk`) y el `name` canónico del usuario.

### 3.3 Ciclo de Vida de Tokens y Session Keys
- **Authentication Tokens (`token`):** Son específicos de la cuenta API, tienen una validez de **60 minutos** desde que se emiten y son de **un solo uso** (se consumen al crear la sesión).
- **Session Keys (`sk`):** Tienen tiempo de vida **infinito** por defecto. Deben almacenarse de forma segura; solo se invalidan si el usuario revoca los permisos desde la configuración de su cuenta en Last.fm.

### 3.4 Parámetros Requeridos en Llamadas Autenticadas
Toda llamada autenticada debe incluir siempre estos tres parámetros:
- **`api_key`** *(Requerido)*: Tu API Key de 32 caracteres.
- **`sk`** *(Requerido)*: La Session Key obtenida de `auth.getSession` o `auth.getMobileSession`.
- **`api_sig`** *(Requerido)*: Firma MD5 de 32 caracteres calculada según la regla 3.5.

### 3.5 Algoritmo de Firma (`api_sig`)
Para construir `api_sig`:
1. Tomar **todos** los parámetros que se van a enviar en la petición (incluyendo `method`, `api_key`, `sk` y los parámetros propios del método), **EXCLUYENDO `format` y `callback`**.
2. Ordenar los parámetros **alfabéticamente por el nombre del parámetro** según el orden de la tabla **ASCII** (sensible a mayúsculas/minúsculas; atención en llamadas por lote: `artist[10]` se ordena antes que `artist[1]`).
3. Concatenar nombre y valor de cada parámetro en una sola cadena continua bajo el esquema `<nombre><valor>` (sin `=`, sin `&`), asegurando codificación **UTF-8**.
4. Añadir al final de esa cadena el **`secret`** de tu cuenta API.
5. Generar el hash **MD5 hexadecimal de 32 caracteres** de la cadena resultante:
   ```text
   Cadena: api_keyxxxxxxxxxxmethodauth.getSessiontokenyyyyyyilovecher
   api_sig = md5("api_keyxxxxxxxxxxmethodauth.getSessiontokenyyyyyyilovecher")
   ```

---

## 4. Reglas de Scrobbling 2.0 (`/api/scrobbling`)

El protocolo Scrobbling 2.0 (no retrocompatible con Submissions 1.2.1) consta de dos métodos de escritura: **`track.updateNowPlaying`** y **`track.scrobble`**.

### 4.1 Flujo y Condiciones para hacer Scrobble
1. **Al iniciar la reproducción (`track.updateNowPlaying`):** Notifica a Last.fm que el usuario empezó a escuchar una pista. Es opcional pero recomendado. Muestra la canción en reproducción en el perfil del usuario, pero **no** suma reproducciones ni afecta los charts.
2. **Condiciones obligatorias para enviar un Scrobble (`track.scrobble`):**
   - La pista debe durar **más de 30 segundos** (`duration > 30`).
   - Y la pista debe haberse reproducido durante **al menos la mitad de su duración** (`>= 50%`) o durante **4 minutos** (lo que ocurra primero).
   - En cuanto se cumplan ambas condiciones, se puede enviar el scrobble (habitualmente al terminar la pista).
3. **Reglas sobre Metadatos y Correcciones:**
   - Nunca adivinar metadatos a partir del nombre del archivo; usar únicamente fuentes estructuradas (como etiquetas ID3).
   - **Prohibido usar automáticamente las correcciones devueltas por `track.updateNowPlaying` como entrada para `track.scrobble`**, salvo que el usuario las haya aprobado explícitamente.
4. **Uso del parámetro `chosenByUser`:**
   - `1` (valor por defecto si se omite): El usuario eligió directamente reproducir esa canción.
   - `0`: La canción fue seleccionada por una fuente externa sin control directo del usuario (ej. radio, stream de recomendaciones, DJ). Si hay duda, omitir el parámetro.
5. **Envío en Lote (Batching) y Caché Persistente:**
   - Si no hay conectividad, el cliente debe guardar los scrobbles pendientes en una caché local persistente que sobreviva a reinicios de la aplicación.
   - Los scrobbles deben enviarse en orden cronológico (primero los cacheados antes que los nuevos).
   - `track.scrobble` permite enviar hasta **50 scrobbles en un solo POST** usando notación de array `param[i]` con `0 <= i <= 49`.

### 4.2 Filtrado de Scrobbling y Correcciones en la Respuesta (`status="ok"`)
Incluso cuando la respuesta es exitosa (`status="ok"`), Last.fm aplica dos procesos internos:
- **Corrección de catálogo (`corrected="0|1"`):** Los nodos `<track>`, `<artist>`, `<album>` y `<albumArtist>` incluyen el atributo `corrected`. Si es `"1"`, el texto dentro del nodo contiene el nombre canónico corregido por Last.fm.
- **Filtrado de metadatos (`ignoredMessage`):** Si Last.fm detecta metadatos inválidos o fuera de rango, ignora el scrobble individualmente (en un lote, los demás scrobbles válidos sí se aceptan) y devuelve el código en `<ignoredMessage code="...">`:
  - `0`: Aceptado (pasó todos los filtros).
  - `1`: Artista filtrado (*Filtered artist*, ej. `"Unknown Artist"`).
  - `2`: Canción filtrada (*Filtered track*).
  - `3`: Timestamp demasiado antiguo (*Timestamp too far in the past*).
  - `4`: Timestamp demasiado en el futuro (*Timestamp too far in the future*).
  - `5`: Límite diario de scrobbles superado (*Max daily scrobbles exceeded*).

---

## 5. Catálogo Detallado de Métodos por Paquete

> En todos los métodos de lectura (`GET`), si usas `LastFmClient` con `format=json`, se indica la **Ruta JSON (`dataKey`)** exacta para extraer los datos de la respuesta.

---

### 5.1 Paquete `auth.*` (3 métodos)

#### 1. `auth.getToken`
Obtiene un token de solicitud no autorizado (Paso 1 para aplicaciones de escritorio; las aplicaciones web no necesitan este método).
- **Verbo HTTP:** `GET` | **Requiere Auth (`sk`):** No (pero **sí** requiere `api_sig`)
- **Parámetros que se necesitan pasar:**
  - `api_key` *(Requerido)*: Clave API de Last.fm.
  - `api_sig` *(Requerido)*: Firma MD5 de los parámetros.
- **Cómo devuelve la información (`dataKey`: `token`):**
  - Devuelve el string hexadecimal de 32 caracteres del token en el nodo `<token>` (en JSON: `{"token": "cf45fe5a3e3cebe168480a086d7fe481"}`).

#### 2. `auth.getSession`
Obtiene la clave de sesión (`sk`) para un usuario una vez que el `token` fue autorizado por el usuario (Paso 3 del flujo de autenticación Web y Desktop).
- **Verbo HTTP:** `GET` | **Requiere Auth (`sk`):** No (requiere `api_sig`)
- **Parámetros que se necesitan pasar:**
  - `token` *(Requerido)*: Hash MD5 hexadecimal de 32 caracteres obtenido tras la autorización del usuario.
  - `api_key` *(Requerido)*: Clave API de Last.fm.
  - `api_sig` *(Requerido)*: Firma MD5 de los parámetros.
- **Cómo devuelve la información (`dataKey`: `session`):**
  - `name`: Nombre de usuario en Last.fm (ej. `"MyLastFMUsername"`).
  - `key`: Clave de sesión (`sk`) de 32 caracteres para usar en futuras llamadas autenticadas.
  - `subscriber`: `"1"` si es suscriptor Pro, `"0"` en caso contrario.

#### 3. `auth.getMobileSession`
Crea una sesión de servicio web pasando credenciales directas del usuario. Exclusivo para dispositivos móviles independientes. *(Nota: el parámetro antiguo `authToken` está deprecado).*
- **Verbo HTTP:** `POST` *(Obligatorio sobre **HTTPS**)* | **Requiere Auth (`sk`):** No (requiere `api_sig`)
- **Parámetros que se necesitan pasar:**
  - `username` *(Requerido)*: Nombre de usuario o dirección de correo electrónico de Last.fm.
  - `password` *(Requerido)*: Contraseña del usuario en texto plano.
  - `api_key` *(Requerido)*: Clave API de Last.fm.
  - `api_sig` *(Requerido)*: Firma MD5 de los parámetros.
- **Cómo devuelve la información (`dataKey`: `session`):**
  - `name`: Nombre de usuario canónico de Last.fm (usar siempre este valor devuelto por si el usuario ingresó su email).
  - `key`: Clave de sesión (`sk`) de 32 caracteres.
  - `subscriber`: `"0"` o `"1"`.

---

### 5.2 Paquete `album.*` (6 métodos)

#### 1. `album.addTags`
Etiqueta un álbum usando una lista de tags definidos por el usuario autenticado.
- **Verbo HTTP:** `POST` | **Requiere Auth:** Sí (`api_key`, `sk`, `api_sig`)
- **Parámetros que se necesitan pasar:**
  - `artist` *(Requerido)*: Nombre del artista.
  - `album` *(Requerido)*: Nombre del álbum.
  - `tags` *(Requerido)*: Lista de tags separados por comas (máximo **10 tags**).
  - `api_key` *(Requerido)*, `api_sig` *(Requerido)*, `sk` *(Requerido)*.
- **Cómo devuelve la información:**
  - Respuesta de confirmación vacía con estado `<lfm status="ok"></lfm>` (`{}` en JSON).

#### 2. `album.getInfo`
Obtiene los metadatos, estadísticas, tags principales, wiki y lista de canciones (*tracklist*) de un álbum usando artista + álbum o su MusicBrainz ID (`mbid`).
- **Verbo HTTP:** `GET` | **Requiere Auth:** No
- **Parámetros que se necesitan pasar:**
  - `artist` *(Requerido, salvo que se envíe `mbid`)*: Nombre del artista.
  - `album` *(Requerido, salvo que se envíe `mbid`)*: Nombre del álbum.
  - `mbid` *(Opcional)*: MusicBrainz ID del álbum.
  - `autocorrect` *(Opcional, `0|1`)*: Si es `1`, transforma nombres de artista mal escritos en su versión canónica corregida.
  - `username` *(Opcional)*: Nombre de usuario para el contexto de la consulta. Si se envía, la respuesta incluye el `userplaycount` de ese usuario para este álbum.
  - `lang` *(Opcional)*: Código de idioma ISO 639 alpha-2 para devolver la biografía/wiki (ej. `es`, `en`).
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `album`):**
  - Datos principales: `name`, `artist` (string), `id`, `mbid`, `url`, `releasedate`, `listeners`, `playcount`, `userplaycount` (si se envió `username`).
  - `image`: Array de objetos `{"#text": "<url>", "size": "small|medium|large|extralarge|mega"}`.
  - `toptags.tag` (o `tags.tag`): Array de objetos tag con `name` y `url`.
  - `tracks.track`: Array de pistas del álbum (Nota: si el álbum tiene 1 sola pista, puede venir como objeto único en lugar de array). Cada `track` contiene:
    - `@attr.rank`: Número de pista en el álbum (`"1"`, `"2"`, ...).
    - `name`: Nombre de la canción.
    - `duration`: Duración en **segundos** (o `null`/vacío si no está disponible).
    - `mbid`: MusicBrainz ID de la pista.
    - `url`: URL de la pista en Last.fm.
    - `streamable`: `{"#text": "0|1", "fulltrack": "0|1"}`.
    - `artist`: Objeto `{"name": "...", "mbid": "...", "url": "..."}`.
  - `wiki` (si existe): Objeto con `published`, `summary` y `content`.

#### 3. `album.getTags`
Obtiene los tags aplicados por un usuario individual a un álbum.
- **Verbo HTTP:** `GET` | **Requiere Auth:** Opcional (si se llama sin autenticar, `user` es obligatorio; si se llama autenticado y se omite `user`, usa el usuario de la sesión).
- **Parámetros que se necesitan pasar:**
  - `artist` *(Requerido, salvo que se envíe `mbid`)*: Nombre del artista.
  - `album` *(Requerido, salvo que se envíe `mbid`)*: Nombre del álbum.
  - `mbid` *(Opcional)*: MusicBrainz ID del álbum.
  - `autocorrect` *(Opcional, `0|1`)*: Corrige el nombre del artista si está mal escrito.
  - `user` *(Opcional si autenticado / Requerido sin autenticar)*: Usuario a consultar.
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `tags` o `tags.tag`):**
  - `@attr`: `{"artist": "...", "album": "..."}`.
  - `tag`: Array de objetos con `name` y `url`.

#### 4. `album.getTopTags`
Obtiene los tags principales globales de un álbum, ordenados por popularidad.
- **Verbo HTTP:** `GET` | **Requiere Auth:** No
- **Parámetros que se necesitan pasar:**
  - `artist` *(Requerido, salvo que se envíe `mbid`)*: Nombre del artista.
  - `album` *(Requerido, salvo que se envíe `mbid`)*: Nombre del álbum.
  - `mbid` *(Opcional)*: MusicBrainz ID del álbum.
  - `autocorrect` *(Opcional, `0|1`)*: Corrige nombres de artista mal escritos.
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `toptags` o `toptags.tag`):**
  - `@attr`: `{"artist": "...", "album": "..."}`.
  - `tag`: Array de objetos con:
    - `name`: Nombre del tag.
    - `count`: Conteo ponderado de frecuencia de uso del tag (máximo `100`).
    - `url`: URL del tag en Last.fm.

#### 5. `album.removeTag`
Elimina un tag aplicado por el usuario autenticado a un álbum.
- **Verbo HTTP:** `POST` | **Requiere Auth:** Sí (`api_key`, `sk`, `api_sig`)
- **Parámetros que se necesitan pasar:**
  - `artist` *(Requerido)*: Nombre del artista.
  - `album` *(Requerido)*: Nombre del álbum.
  - `tag` *(Requerido)*: Un único tag de usuario a eliminar del álbum.
  - `api_key` *(Requerido)*, `api_sig` *(Requerido)*, `sk` *(Requerido)*.
- **Cómo devuelve la información:**
  - Confirmación vacía `<lfm status="ok"></lfm>`.

#### 6. `album.search`
Busca un álbum por nombre y devuelve coincidencias ordenadas por relevancia.
- **Verbo HTTP:** `GET` | **Requiere Auth:** No
- **Parámetros que se necesitan pasar:**
  - `album` *(Requerido)*: Nombre del álbum a buscar.
  - `limit` *(Opcional)*: Resultados por página (por defecto `30`).
  - `page` *(Opcional)*: Número de página a consultar (por defecto `1`).
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `results` o `results.albummatches.album`):**
  - Metadatos OpenSearch en `results`: `opensearch:Query` (`role`, `searchTerms`, `startPage`), `opensearch:totalResults`, `opensearch:startIndex`, `opensearch:itemsPerPage`, `@attr.for`.
  - `albummatches.album`: Array de álbumes con `name`, `artist` (string), `id`, `mbid`, `url`, `streamable` (`"0|1"`), e `image` (array por `size`: `small`, `medium`, `large`, `extralarge`).

---

### 5.3 Paquete `artist.*` (10 métodos)

#### 1. `artist.addTags`
Etiqueta a un artista con uno o más tags definidos por el usuario.
- **Verbo HTTP:** `POST` | **Requiere Auth:** Sí (`api_key`, `sk`, `api_sig`)
- **Parámetros que se necesitan pasar:**
  - `artist` *(Requerido)*: Nombre del artista.
  - `tags` *(Requerido)*: Lista de tags separados por comas (máximo **10 tags**).
  - `api_key` *(Requerido)*, `api_sig` *(Requerido)*, `sk` *(Requerido)*.
- **Cómo devuelve la información:**
  - Confirmación vacía `<lfm status="ok"></lfm>`.

#### 2. `artist.getCorrection`
Consulta los datos de corrección de Last.fm para verificar si el nombre de artista enviado tiene una corrección hacia un artista canónico.
- **Verbo HTTP:** `GET` | **Requiere Auth:** No
- **Parámetros que se necesitan pasar:**
  - `artist` *(Requerido)*: Nombre del artista a verificar/corregir.
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `corrections.correction`):**
  - `@attr.index`: Índice de la corrección (ej. `"0"`).
  - `artist`: Objeto con el artista canónico: `name`, `mbid`, `url`.

#### 3. `artist.getInfo`
Obtiene los metadatos de un artista, incluyendo estadísticas, artistas similares, tags principales y biografía (el resumen se trunca a 300 caracteres).
- **Verbo HTTP:** `GET` | **Requiere Auth:** No
- **Parámetros que se necesitan pasar:**
  - `artist` *(Requerido, salvo que se envíe `mbid`)*: Nombre del artista.
  - `mbid` *(Opcional)*: MusicBrainz ID del artista.
  - `lang` *(Opcional)*: Código de idioma ISO 639 alpha-2 para la biografía.
  - `autocorrect` *(Opcional, `0|1`)*: Transforma nombres mal escritos en el nombre corregido.
  - `username` *(Opcional)*: Si se envía, incluye `stats.userplaycount` del usuario para este artista.
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `artist`):**
  - Datos base: `name`, `mbid`, `url`, `streamable` (`"0|1"`), `ontour` (`"0|1"`), `image` (array por `size`).
  - `stats`: Objeto con `listeners`, `playcount` (en XML `<plays>` o `<playcount>`) y opcionalmente `userplaycount`.
  - `similar.artist`: Array de artistas similares, cada uno con `name`, `url` e `image`.
  - `tags.tag`: Array de tags principales con `name` y `url`.
  - `bio`: Objeto con `links`, `published`, `summary` (truncado a 300 caracteres) y `content`.

#### 4. `artist.getSimilar`
Obtiene todos los artistas similares a un artista dado.
- **Verbo HTTP:** `GET` | **Requiere Auth:** No
- **Parámetros que se necesitan pasar:**
  - `artist` *(Requerido, salvo que se envíe `mbid`)*: Nombre del artista.
  - `mbid` *(Opcional)*: MusicBrainz ID del artista.
  - `autocorrect` *(Opcional, `0|1`)*: Corrige el nombre del artista si está mal escrito.
  - `limit` *(Opcional)*: Límite de artistas similares a devolver.
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `similarartists` o `similarartists.artist`):**
  - `@attr.artist`: Nombre del artista consultado.
  - `artist`: Array de objetos con:
    - `name`, `mbid`, `url`, `streamable` (`"0|1"`), `image` (por `size`: `small`, `medium`, `large`, `extralarge`, `mega`).
    - `match`: Valor decimal de similitud entre `0` (nada similar) y `1` (muy similar).

#### 5. `artist.getTags`
Obtiene los tags aplicados por un usuario individual a un artista.
- **Verbo HTTP:** `GET` | **Requiere Auth:** Opcional (si es autenticado y no se envía `user`, devuelve los tags del usuario autenticado; sin autenticar, `user` es obligatorio).
- **Parámetros que se necesitan pasar:**
  - `artist` *(Requerido, salvo que se envíe `mbid`)*: Nombre del artista.
  - `mbid` *(Opcional)*: MusicBrainz ID del artista.
  - `user` *(Opcional si autenticado / Requerido sin autenticar)*: Usuario a consultar.
  - `autocorrect` *(Opcional, `0|1`)*: Corrige el nombre del artista.
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `tags` o `tags.tag`):**
  - `@attr.artist`: Nombre del artista.
  - `tag`: Array de objetos con `name` y `url`.

#### 6. `artist.getTopAlbums`
Obtiene los álbumes más populares de un artista en Last.fm, ordenados por popularidad.
- **Verbo HTTP:** `GET` | **Requiere Auth:** No
- **Parámetros que se necesitan pasar:**
  - `artist` *(Requerido, salvo que se envíe `mbid`)*: Nombre del artista.
  - `mbid` *(Opcional)*: MusicBrainz ID del artista.
  - `autocorrect` *(Opcional, `0|1`)*: Corrige el nombre del artista.
  - `page` *(Opcional)*: Número de página (por defecto `1`).
  - `limit` *(Opcional)*: Resultados por página (por defecto `50`).
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `topalbums` o `topalbums.album`):**
  - `@attr`: `artist`, `page`, `perPage`, `totalPages`, `total`.
  - `album`: Array de objetos con `@attr.rank`, `name`, `mbid`, `playcount` / `listeners`, `url`, `artist` (`name`, `mbid`, `url`) e `image` (array por `size`).

#### 7. `artist.getTopTags`
Obtiene los tags globales más populares de un artista.
- **Verbo HTTP:** `GET` | **Requiere Auth:** No
- **Parámetros que se necesitan pasar:**
  - `artist` *(Requerido, salvo que se envíe `mbid`)*: Nombre del artista.
  - `mbid` *(Opcional)*: MusicBrainz ID del artista.
  - `autocorrect` *(Opcional, `0|1`)*: Corrige el nombre del artista.
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `toptags` o `toptags.tag`):**
  - `@attr.artist`: Nombre del artista.
  - `tag`: Array de objetos con `name`, `count` (peso hasta 100) y `url`.

#### 8. `artist.getTopTracks`
Obtiene las canciones más populares de un artista en Last.fm, ordenadas por popularidad.
- **Verbo HTTP:** `GET` | **Requiere Auth:** No
- **Parámetros que se necesitan pasar:**
  - `artist` *(Requerido, salvo que se envíe `mbid`)*: Nombre del artista.
  - `mbid` *(Opcional)*: MusicBrainz ID del artista.
  - `autocorrect` *(Opcional, `0|1`)*: Corrige el nombre del artista.
  - `page` *(Opcional)*: Número de página (por defecto `1`).
  - `limit` *(Opcional)*: Resultados por página (por defecto `50`).
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `toptracks` o `toptracks.track`):**
  - `@attr`: `artist`, `page`, `perPage`, `totalPages`, `total`.
  - `track`: Array de objetos con `@attr.rank`, `name`, `mbid`, `playcount`, `listeners`, `streamable`, `url`, `artist` (`name`, `mbid`, `url`) e `image` (array por `size`).

#### 9. `artist.removeTag`
Elimina un tag aplicado por el usuario autenticado a un artista.
- **Verbo HTTP:** `POST` | **Requiere Auth:** Sí (`api_key`, `sk`, `api_sig`)
- **Parámetros que se necesitan pasar:**
  - `artist` *(Requerido)*: Nombre del artista.
  - `tag` *(Requerido)*: Un único tag a eliminar.
  - `api_key` *(Requerido)*, `api_sig` *(Requerido)*, `sk` *(Requerido)*.
- **Cómo devuelve la información:**
  - Confirmación vacía `<lfm status="ok"></lfm>`.

#### 10. `artist.search`
Busca un artista por nombre y devuelve coincidencias ordenadas por relevancia.
- **Verbo HTTP:** `GET` | **Requiere Auth:** No
- **Parámetros que se necesitan pasar:**
  - `artist` *(Requerido)*: Nombre del artista a buscar.
  - `limit` *(Opcional)*: Resultados por página (por defecto `30`).
  - `page` *(Opcional)*: Número de página (por defecto `1`).
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `results` o `results.artistmatches.artist`):**
  - Metadatos OpenSearch (`opensearch:Query` con `role`, `searchTerms`, `startPage`; `opensearch:totalResults`, `opensearch:startIndex`, `opensearch:itemsPerPage`, `@attr.for`).
  - `artistmatches.artist`: Array de artistas con `name`, `listeners`, `mbid`, `url`, `streamable` (`"0|1"`) e `image` (array por `size`, y en XML clásico `<image_small>` / `<image>`).

---

### 5.4 Paquete `tag.*` (7 métodos)

#### 1. `tag.getInfo`
Obtiene los metadatos y la wiki descriptiva de un tag (género/etiqueta).
- **Verbo HTTP:** `GET` | **Requiere Auth:** No
- **Parámetros que se necesitan pasar:**
  - `tag` *(Requerido)*: Nombre del tag.
  - `lang` *(Opcional)*: Idioma para la wiki en formato ISO 639 alpha-2.
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `tag`):**
  - `name`: Nombre del tag.
  - `url` *(en XML)*: URL del tag.
  - `reach`: Número de usuarios distintos que han usado este tag.
  - `total` / `taggings`: Número total de veces que se ha aplicado este tag.
  - `streamable` *(en XML)*: `"1"` indica que puede usarse como estación de radio.
  - `wiki`: Objeto con `published`, `summary` y `content`.

#### 2. `tag.getSimilar`
Busca tags similares al tag indicado, clasificados por similitud según datos de escucha.
- **Verbo HTTP:** `GET` | **Requiere Auth:** No
- **Parámetros que se necesitan pasar:**
  - `tag` *(Requerido)*: Nombre del tag.
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `similartags` o `similartags.tag`):**
  - `@attr.tag`: Nombre del tag consultado.
  - `tag`: Array de objetos con `name`, `url` y `streamable` (`"0|1"`).

#### 3. `tag.getTopAlbums`
Obtiene los álbumes principales etiquetados con este tag, ordenados por conteo de uso del tag.
- **Verbo HTTP:** `GET` | **Requiere Auth:** No
- **Parámetros que se necesitan pasar:**
  - `tag` *(Requerido)*: Nombre del tag.
  - `limit` *(Opcional)*: Resultados por página (por defecto `50`).
  - `page` *(Opcional)*: Número de página (por defecto `1`).
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `albums` o `topalbums` -> `.album`):**
  - `@attr`: `tag`, `page`, `perPage`, `totalPages`, `total`.
  - `album`: Array de objetos con `@attr.rank`, `name`, `mbid`, `url`, `artist` (`name`, `mbid`, `url`) e `image` (array por `size`).

#### 4. `tag.getTopArtists`
Obtiene los artistas principales etiquetados con este tag, ordenados por conteo de uso del tag.
- **Verbo HTTP:** `GET` | **Requiere Auth:** No
- **Parámetros que se necesitan pasar:**
  - `tag` *(Requerido)*: Nombre del tag.
  - `limit` *(Opcional)*: Resultados por página (por defecto `50`).
  - `page` *(Opcional)*: Número de página (por defecto `1`).
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `topartists` o `topartists.artist`):**
  - `@attr`: `tag`, `page`, `perPage`, `totalPages`, `total`.
  - `artist`: Array de objetos con `@attr.rank`, `name`, `mbid`, `url`, `streamable` (`"0|1"`) e `image` (array por `size`).

#### 5. `tag.getTopTags`
Obtiene los tags globales más populares en todo Last.fm, ordenados por popularidad (número de veces usados).
- **Verbo HTTP:** `GET` | **Requiere Auth:** No
- **Parámetros que se necesitan pasar:**
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `toptags` o `toptags.tag`):**
  - `tag`: Array de objetos con `name`, `count` (total de usos) y `url` / `reach`.

#### 6. `tag.getTopTracks`
Obtiene las canciones principales etiquetadas con este tag, ordenadas por conteo de uso del tag.
- **Verbo HTTP:** `GET` | **Requiere Auth:** No
- **Parámetros que se necesitan pasar:**
  - `tag` *(Requerido)*: Nombre del tag.
  - `limit` *(Opcional)*: Resultados por página (por defecto `50`).
  - `page` *(Opcional)*: Número de página (por defecto `1`).
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `tracks` o `toptracks` -> `.track`):**
  - `@attr`: `tag`, `page`, `perPage`, `totalPages`, `total`.
  - `track`: Array de objetos con `@attr.rank`, `name`, `duration`, `mbid`, `url`, `streamable` (`#text`, `fulltrack`), `artist` (`name`, `mbid`, `url`) e `image` (array por `size`).

#### 7. `tag.getWeeklyChartList`
Obtiene la lista de rangos de fechas de charts semanales disponibles para este tag.
- **Verbo HTTP:** `GET` | **Requiere Auth:** No
- **Parámetros que se necesitan pasar:**
  - `tag` *(Requerido)*: Nombre del tag.
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `weeklychartlist.chart`):**
  - `@attr.tag`: Nombre del tag.
  - `chart`: Array de objetos con `@attr.from` y `@attr.to` (o `from` y `to` en conversión directa de atributos), expresados en timestamps UNIX.

---

### 5.5 Paquete `track.*` (12 métodos)

#### 1. `track.addTags`
Etiqueta una canción usando una lista de tags definidos por el usuario.
- **Verbo HTTP:** `POST` | **Requiere Auth:** Sí (`api_key`, `sk`, `api_sig`)
- **Parámetros que se necesitan pasar:**
  - `artist` *(Requerido)*: Nombre del artista.
  - `track` *(Requerido)*: Nombre de la canción.
  - `tags` *(Requerido)*: Lista de tags separados por comas (máximo **10 tags**).
  - `api_key` *(Requerido)*, `api_sig` *(Requerido)*, `sk` *(Requerido)*.
- **Cómo devuelve la información:**
  - Confirmación vacía `<lfm status="ok"></lfm>`.

#### 2. `track.getCorrection`
Usa los datos de corrección de Last.fm para verificar si la canción y artista enviados tienen una corrección hacia una pista canónica.
- **Verbo HTTP:** `GET` | **Requiere Auth:** No
- **Parámetros que se necesitan pasar:**
  - `artist` *(Requerido)*: Nombre del artista a verificar.
  - `track` *(Requerido)*: Nombre de la canción a verificar.
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `corrections.correction`):**
  - `@attr`: `index` (`"0"`), `artistcorrected` (`"0|1"`), `trackcorrected` (`"0|1"`).
  - `track`: Objeto con `name`, `mbid`, `url` y subobjeto `artist` (`name`, `mbid`, `url`).

#### 3. `track.getInfo`
Obtiene los metadatos completos de una canción usando artista + nombre de pista o su `mbid`.
- **Verbo HTTP:** `GET` | **Requiere Auth:** No
- **Parámetros que se necesitan pasar:**
  - `track` *(Requerido, salvo que se envíe `mbid`)*: Nombre de la canción.
  - `artist` *(Requerido, salvo que se envíe `mbid`)*: Nombre del artista.
  - `mbid` *(Opcional)*: MusicBrainz ID de la canción.
  - `username` *(Opcional)*: Nombre de usuario para el contexto de la petición. Si se envía, la respuesta incluye `userplaycount` y `userloved` (`"0|1"`).
  - `autocorrect` *(Opcional, `0|1`)*: Corrige nombres de artista y canción mal escritos, devolviendo la versión corregida.
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `track`):**
  - Datos principales: `id`, `name`, `mbid`, `url`.
  - `duration`: Duración de la canción en **milisegundos** *(ojo: en `track.getInfo` viene en milisegundos, ej. `"240000"`, mientras que en `album.getInfo` y `track.scrobble` se maneja en segundos)*.
  - `streamable`: `{"#text": "0|1", "fulltrack": "0|1"}` (`#text = 1` indica preview de 30s disponible; `fulltrack = 1` indica pista completa disponible).
  - `listeners`, `playcount`, y si se envió `username`: `userplaycount` y `userloved` (`"0|1"`).
  - `artist`: Objeto con `name`, `mbid`, `url`.
  - `album` *(si la pista está asociada a un álbum)*: Objeto con `@attr.position` (posición en el álbum), `artist` (string), `title` (nombre del álbum), `mbid`, `url` e `image` (array por `size`).
  - `toptags.tag`: Array de tags principales con `name` y `url`.
  - `wiki` *(si existe)*: Objeto con `published`, `summary` y `content`.

#### 4. `track.getSimilar`
Obtiene las canciones similares a una canción dada según datos de escucha.
- **Verbo HTTP:** `GET` | **Requiere Auth:** No
- **Parámetros que se necesitan pasar:**
  - `track` *(Requerido, salvo que se envíe `mbid`)*: Nombre de la canción.
  - `artist` *(Requerido, salvo que se envíe `mbid`)*: Nombre del artista.
  - `mbid` *(Opcional)*: MusicBrainz ID de la canción.
  - `autocorrect` *(Opcional, `0|1`)*: Corrige nombres de artista y canción mal escritos.
  - `limit` *(Opcional)*: Número máximo de pistas similares a devolver.
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `similartracks` o `similartracks.track`):**
  - `@attr`: `track` y `artist`.
  - `track`: Array de pistas similares con `name`, `playcount`, `mbid`, `match` (puntaje de similitud), `url`, `streamable` (`#text`, `fulltrack`), `duration`, `artist` (`name`, `mbid`, `url`) e `image` (array por `size`).

#### 5. `track.getTags`
Obtiene los tags aplicados por un usuario individual a una canción.
- **Verbo HTTP:** `GET` | **Requiere Auth:** Opcional (si se llama sin autenticar, `user` es obligatorio).
- **Parámetros que se necesitan pasar:**
  - `artist` *(Requerido, salvo que se envíe `mbid`)*: Nombre del artista.
  - `track` *(Requerido, salvo que se envíe `mbid`)*: Nombre de la canción.
  - `mbid` *(Opcional)*: MusicBrainz ID de la canción.
  - `autocorrect` *(Opcional, `0|1`)*: Corrige nombres de artista y canción.
  - `user` *(Opcional si autenticado / Requerido sin autenticar)*: Usuario a consultar.
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `tags` o `tags.tag`):**
  - `@attr`: `artist` y `track`.
  - `tag`: Array de objetos con `name` y `url`.

#### 6. `track.getTopTags`
Obtiene los tags globales más populares para una canción, ordenados por conteo de uso.
- **Verbo HTTP:** `GET` | **Requiere Auth:** No
- **Parámetros que se necesitan pasar:**
  - `track` *(Requerido, salvo que se envíe `mbid`)*: Nombre de la canción.
  - `artist` *(Requerido, salvo que se envíe `mbid`)*: Nombre del artista.
  - `mbid` *(Opcional)*: MusicBrainz ID de la canción.
  - `autocorrect` *(Opcional, `0|1`)*: Corrige nombres de artista y canción.
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `toptags` o `toptags.tag`):**
  - `@attr`: `artist` y `track`.
  - `tag`: Array de objetos con `name`, `count` (peso hasta 100) y `url`.

#### 7. `track.love`
Marca una canción como favorita (*Loved*) en el perfil del usuario autenticado.
- **Verbo HTTP:** `POST` | **Requiere Auth:** Sí (`api_key`, `sk`, `api_sig`)
- **Parámetros que se necesitan pasar:**
  - `track` *(Requerido)*: Nombre de la canción (codificado en UTF-8).
  - `artist` *(Requerido)*: Nombre del artista (codificado en UTF-8).
  - `api_key` *(Requerido)*, `api_sig` *(Requerido)*, `sk` *(Requerido)*.
- **Cómo devuelve la información:**
  - Confirmación vacía `<lfm status="ok"></lfm>`.

#### 8. `track.removeTag`
Elimina un tag aplicado por el usuario autenticado a una canción.
- **Verbo HTTP:** `POST` | **Requiere Auth:** Sí (`api_key`, `sk`, `api_sig`)
- **Parámetros que se necesitan pasar:**
  - `artist` *(Requerido)*: Nombre del artista.
  - `track` *(Requerido)*: Nombre de la canción.
  - `tag` *(Requerido)*: Un único tag a eliminar de la canción.
  - `api_key` *(Requerido)*, `api_sig` *(Requerido)*, `sk` *(Requerido)*.
- **Cómo devuelve la información:**
  - Confirmación vacía `<lfm status="ok"></lfm>`.

#### 9. `track.scrobble`
Añade una reproducción (*track-play*) o un lote de hasta **50 reproducciones** (`0 <= i <= 49`) al perfil del usuario. Si se envía un único scrobble, se puede omitir el sufijo `[i]`. Los nombres de los parámetros son **sensibles a mayúsculas/minúsculas** (`albumArtist`, `trackNumber`, `chosenByUser`, `streamId`).
- **Verbo HTTP:** `POST` | **Requiere Auth:** Sí (`api_key`, `sk`, `api_sig`)
- **Parámetros que se necesitan pasar:**
  - `artist[i]` *(Requerido)*: Nombre del artista.
  - `track[i]` *(Requerido)*: Nombre de la canción.
  - `timestamp[i]` *(Requerido)*: Momento en que **empezó** a reproducirse la canción, en formato UNIX timestamp (segundos enteros desde `1970-01-01 00:00:00 UTC`).
  - `album[i]` *(Opcional)*: Nombre del álbum.
  - `context[i]` *(Opcional)*: Versión del sub-cliente (no público, habilitado solo para ciertas API keys).
  - `streamId[i]` *(Opcional)*: Stream ID recibido de `radio.getPlaylist` si se hace scrobble de radio Last.fm.
  - `chosenByUser[i]` *(Opcional, `0|1`)*: `1` si el usuario eligió la canción (por defecto `1`), `0` si fue elegida por un tercero (radio/recomendación).
  - `trackNumber[i]` *(Opcional)*: Número de pista dentro del álbum.
  - `mbid[i]` *(Opcional)*: MusicBrainz Track ID.
  - `albumArtist[i]` *(Opcional)*: Artista del álbum, si difiere del artista de la pista.
  - `duration[i]` *(Opcional)*: Duración de la pista en **segundos**.
  - `api_key` *(Requerido)*, `api_sig` *(Requerido)*, `sk` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `scrobbles`):**
  - `@attr`: `accepted` (número de scrobbles aceptados) e `ignored` (número de scrobbles ignorados).
  - `scrobble`: Objeto (si se envió 1) o Array de objetos (si se enviaron varios), cada uno con:
    - `track`: `{"corrected": "0|1", "#text": "Nombre de la pista"}`
    - `artist`: `{"corrected": "0|1", "#text": "Nombre del artista"}`
    - `album`: `{"corrected": "0|1", "#text": "Nombre del álbum"}`
    - `albumArtist`: `{"corrected": "0|1", "#text": "Artista del álbum"}`
    - `timestamp`: Timestamp UNIX enviado.
    - `ignoredMessage`: `{"code": "0|1|2|3|4|5", "#text": "Mensaje si fue ignorado"}`.

#### 10. `track.search`
Busca una canción por nombre y devuelve coincidencias ordenadas por relevancia.
- **Verbo HTTP:** `GET` | **Requiere Auth:** No
- **Parámetros que se necesitan pasar:**
  - `track` *(Requerido)*: Nombre de la canción a buscar.
  - `artist` *(Opcional)*: Acota la búsqueda especificando el nombre de un artista.
  - `limit` *(Opcional)*: Resultados por página (por defecto `30`).
  - `page` *(Opcional)*: Número de página (por defecto `1`).
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `results` o `results.trackmatches.track`):**
  - Metadatos OpenSearch (`opensearch:Query` con `role`, `searchTerms`, `startPage`; `opensearch:totalResults`, `opensearch:startIndex`, `opensearch:itemsPerPage`, `@attr.for`).
  - `trackmatches.track`: Array de pistas con `name`, `artist` (string), `url`, `streamable` (`#text`, `fulltrack`), `listeners`, `mbid` e `image` (array por `size`).

#### 11. `track.unlove`
Desmarca una canción como favorita (*UnLove*) en el perfil del usuario autenticado.
- **Verbo HTTP:** `POST` | **Requiere Auth:** Sí (`api_key`, `sk`, `api_sig`)
- **Parámetros que se necesitan pasar:**
  - `track` *(Requerido)*: Nombre de la canción (codificado en UTF-8).
  - `artist` *(Requerido)*: Nombre del artista (codificado en UTF-8).
  - `api_key` *(Requerido)*, `api_sig` *(Requerido)*, `sk` *(Requerido)*.
- **Cómo devuelve la información:**
  - Confirmación vacía `<lfm status="ok"></lfm>`.

#### 12. `track.updateNowPlaying`
Notifica a Last.fm que un usuario ha comenzado a escuchar una canción. Los nombres de parámetros son sensibles a mayúsculas/minúsculas (`trackNumber`, `albumArtist`).
- **Verbo HTTP:** `POST` | **Requiere Auth:** Sí (`api_key`, `sk`, `api_sig`)
- **Parámetros que se necesitan pasar:**
  - `artist` *(Requerido)*: Nombre del artista.
  - `track` *(Requerido)*: Nombre de la canción.
  - `album` *(Opcional)*: Nombre del álbum.
  - `trackNumber` *(Opcional)*: Número de pista en el álbum.
  - `context` *(Opcional)*: Versión del sub-cliente.
  - `mbid` *(Opcional)*: MusicBrainz Track ID.
  - `duration` *(Opcional)*: Duración de la canción en **segundos**.
  - `albumArtist` *(Opcional)*: Artista del álbum si difiere del artista de la pista.
  - `api_key` *(Requerido)*, `api_sig` *(Requerido)*, `sk` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `nowplaying`):**
  - `track`: `{"corrected": "0|1", "#text": "..."}`
  - `artist`: `{"corrected": "0|1", "#text": "..."}`
  - `album`: `{"corrected": "0|1", "#text": "..."}`
  - `albumArtist`: `{"corrected": "0|1", "#text": "..."}`
  - `ignoredMessage`: `{"code": "0|1|2|3|4|5", "#text": "..."}`

---

### 5.6 Paquete `user.*` (12 métodos — excluido `user.getFriends`)

#### 1. `user.getInfo`
Obtiene la información del perfil de un usuario.
- **Verbo HTTP:** `GET` | **Requiere Auth:** Opcional (si se omite `user`, devuelve el usuario autenticado).
- **Parámetros que se necesitan pasar:**
  - `user` *(Opcional si autenticado / Requerido sin autenticar)*: Nombre de usuario de Last.fm a consultar.
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `user`):**
  - `name`: Nombre de usuario.
  - `realname`: Nombre real configurado.
  - `id` *(en XML)*: ID numérico del usuario.
  - `url`: URL del perfil en Last.fm.
  - `image`: Array de avatares por tamaño (`small`, `medium`, `large`, `extralarge`).
  - `country`: País del usuario.
  - `age`, `gender`: Edad y género.
  - `subscriber`: `"0|1"` (si es suscriptor Pro).
  - `playcount`: Total histórico de scrobbles del usuario (además en JSON moderno suele incluir `artist_count`, `track_count`, `album_count`).
  - `playlists`: Número de playlists.
  - `bootstrap`: `"0|1"`.
  - `registered`: Objeto con `unixtime` (timestamp UNIX de registro) y `#text` (fecha formateada).

#### 2. `user.getLovedTracks`
Obtiene la lista paginada de canciones marcadas como favoritas (*loved*) por un usuario.
- **Verbo HTTP:** `GET` | **Requiere Auth:** No
- **Parámetros que se necesitan pasar:**
  - `user` *(Requerido)*: Nombre de usuario de Last.fm.
  - `limit` *(Opcional)*: Resultados por página (por defecto `50`).
  - `page` *(Opcional)*: Número de página (por defecto `1`).
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `lovedtracks` o `lovedtracks.track`):**
  - `@attr`: `user`, `page`, `perPage`, `totalPages`, `total`.
  - `track`: Array de canciones favoritas, cada una con:
    - `name`, `mbid`, `url`, `streamable` (`#text`, `fulltrack`), `image` (array por `size`).
    - `date`: `{"uts": "<unix_timestamp>", "#text": "18 Jul 2008, 08:58"}` (fecha en que se marcó como favorita).
    - `artist`: Objeto `{"name": "...", "mbid": "...", "url": "..."}`.

#### 3. `user.getPersonalTags`
Obtiene los ítems (artistas, álbumes o canciones) que un usuario ha etiquetado personalmente con un tag específico.
- **Verbo HTTP:** `GET` | **Requiere Auth:** No
- **Parámetros que se necesitan pasar:**
  - `user` *(Requerido)*: Usuario que aplicó los tags.
  - `tag` *(Requerido)*: Nombre del tag que interesa consultar.
  - `taggingtype` *(Requerido, `artist|album|track`)*: Tipo de entidad etiquetada.
  - `limit` *(Opcional)*: Resultados por página (por defecto `50`).
  - `page` *(Opcional)*: Número de página (por defecto `1`).
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `taggings`):**
  - `@attr`: `user`, `tag`, `page`, `perPage`, `totalPages`, `total`.
  - Dependiendo de `taggingtype`, incluye uno de los siguientes contenedores:
    - `artists.artist`: Array de artistas (`name`, `mbid`, `url`, `streamable`, `image` por `size`).
    - `albums.album`: Array de álbumes (`name`, `mbid`, `url`, `artist`, `image` por `size`).
    - `tracks.track`: Array de canciones (`name`, `duration`, `mbid`, `url`, `streamable`, `artist`, `image` por `size`).

#### 4. `user.getRecentTracks`
Obtiene la lista de canciones escuchadas recientemente por un usuario. Si el usuario está escuchando música en este instante, incluye en primer lugar la pista actual con el atributo `nowplaying="true"`.
- **Verbo HTTP:** `GET` | **Requiere Auth:** No
- **Parámetros que se necesitan pasar:**
  - `user` *(Requerido)*: Nombre de usuario de Last.fm.
  - `limit` *(Opcional)*: Resultados por página (por defecto `50`, **máximo `200`**).
  - `page` *(Opcional)*: Número de página (por defecto `1`).
  - `from` *(Opcional)*: Timestamp UNIX inicial (UTC) — solo muestra scrobbles posteriores a este momento.
  - `to` *(Opcional)*: Timestamp UNIX final (UTC) — solo muestra scrobbles anteriores a este momento.
  - `extended` *(Opcional, `0|1`)*: Si es `1`, incluye datos extendidos en cada artista (como `image` y `name`) e indica si el usuario marcó cada pista como favorita (`loved`: `"0|1"`).
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `recenttracks` o `recenttracks.track`):**
  - `@attr`: `user`, `page`, `perPage`, `totalPages`, `total`.
  - `track`: Array de pistas escuchadas:
    - `@attr.nowplaying`: `"true"` (presente **únicamente** en la pista que se está reproduciendo en vivo; las pistas en reproducción no traen nodo `date`).
    - `name`: Nombre de la canción.
    - `mbid`: MusicBrainz ID de la pista.
    - `url`: URL de la pista.
    - `streamable`: `"0|1"`.
    - `image`: Array de imágenes por `size`.
    - `album`: `{"mbid": "...", "#text": "Nombre del álbum"}`.
    - `date` *(en pistas ya reproducidas)*: `{"uts": "1213031819", "#text": "9 Jun 2008, 17:16"}`.
    - `artist`:
      - Sin `extended` (o `extended=0`): `{"mbid": "...", "#text": "Nombre del artista"}`.
      - Con `extended=1`: `{"url": "...", "name": "...", "image": [...], "mbid": "..."}` y además en el `track` aparece `"loved": "0|1"`.

#### 5. `user.getTopAlbums`
Obtiene los álbumes más escuchados por un usuario en un periodo de tiempo determinado (por defecto devuelve el histórico completo `overall`).
- **Verbo HTTP:** `GET` | **Requiere Auth:** No
- **Parámetros que se necesitan pasar:**
  - `user` *(Requerido)*: Nombre de usuario.
  - `period` *(Opcional)*: `overall` | `7day` | `1month` | `3month` | `6month` | `12month` (por defecto `overall`).
  - `limit` *(Opcional)*: Resultados por página (por defecto `50`).
  - `page` *(Opcional)*: Número de página (por defecto `1`).
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `topalbums` o `topalbums.album`):**
  - `@attr`: `user`, `type` (en XML, ej. `overall`), `page`, `perPage`, `totalPages`, `total`.
  - `album`: Array de objetos con `@attr.rank`, `name`, `playcount`, `mbid`, `url`, `artist` (`name`, `mbid`, `url`) e `image` (array por `size`).

#### 6. `user.getTopArtists`
Obtiene los artistas más escuchados por un usuario en un periodo de tiempo determinado.
- **Verbo HTTP:** `GET` | **Requiere Auth:** No
- **Parámetros que se necesitan pasar:**
  - `user` *(Requerido)*: Nombre de usuario.
  - `period` *(Opcional)*: `overall` | `7day` | `1month` | `3month` | `6month` | `12month` (por defecto `overall`).
  - `limit` *(Opcional)*: Resultados por página (por defecto `50`).
  - `page` *(Opcional)*: Número de página (por defecto `1`).
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `topartists` o `topartists.artist`):**
  - `@attr`: `user`, `type` (en XML, ej. `overall`), `page`, `perPage`, `totalPages`, `total`.
  - `artist`: Array de objetos con `@attr.rank`, `name`, `playcount`, `mbid`, `url`, `streamable` (`"0|1"`) e `image` (array por `size`).

#### 7. `user.getTopTags`
Obtiene los tags más utilizados por un usuario.
- **Verbo HTTP:** `GET` | **Requiere Auth:** No
- **Parámetros que se necesitan pasar:**
  - `user` *(Requerido)*: Nombre de usuario.
  - `limit` *(Opcional)*: Limita el número de tags devueltos.
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `toptags` o `toptags.tag`):**
  - `@attr.user`: Nombre de usuario.
  - `tag`: Array de objetos con `name`, `count` (veces que el usuario usó el tag) y `url`.

#### 8. `user.getTopTracks`
Obtiene las canciones más escuchadas por un usuario en un periodo de tiempo determinado.
- **Verbo HTTP:** `GET` | **Requiere Auth:** No
- **Parámetros que se necesitan pasar:**
  - `user` *(Requerido)*: Nombre de usuario.
  - `period` *(Opcional)*: `overall` | `7day` | `1month` | `3month` | `6month` | `12month` (por defecto `overall`).
  - `limit` *(Opcional)*: Resultados por página (por defecto `50`).
  - `page` *(Opcional)*: Número de página (por defecto `1`).
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `toptracks` o `toptracks.track`):**
  - `@attr`: `user`, `type` (en XML, ej. `overall`), `page`, `perPage`, `totalPages`, `total`.
  - `track`: Array de objetos con `@attr.rank`, `name`, `duration`, `playcount`, `mbid`, `url`, `streamable` (`#text`, `fulltrack`), `artist` (`name`, `mbid`, `url`) e `image` (array por `size`).

#### 9. `user.getWeeklyAlbumChart`
Obtiene el chart de álbumes de un usuario para un rango de fechas específico. Si no se envían fechas (`from` y `to`), devuelve el chart semanal más reciente del usuario.
- **Verbo HTTP:** `GET` | **Requiere Auth:** No
- **Parámetros que se necesitan pasar:**
  - `user` *(Requerido)*: Nombre de usuario de Last.fm.
  - `from` *(Opcional)*: Timestamp UNIX de inicio del chart (obtenido de `user.getWeeklyChartList`).
  - `to` *(Opcional)*: Timestamp UNIX de fin del chart (obtenido de `user.getWeeklyChartList`).
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `weeklyalbumchart` o `weeklyalbumchart.album`):**
  - `@attr`: `{"user": "...", "from": "1212321600", "to": "1212926400"}`.
  - `album`: Array de objetos con:
    - `@attr.rank`: Posición en el ranking semanal (`"1"`, `"2"`, ...).
    - `artist`: `{"mbid": "...", "#text": "Nombre del artista"}` *(usa `#text` porque `mbid` viene como atributo XML)*.
    - `name`: Nombre del álbum.
    - `mbid`: MusicBrainz ID del álbum.
    - `playcount`: Reproducciones del álbum en esa semana.
    - `url`: URL del álbum en Last.fm.

#### 10. `user.getWeeklyArtistChart`
Obtiene el chart de artistas de un usuario para un rango de fechas específico. Si no se envían `from` y `to`, devuelve el chart semanal más reciente.
- **Verbo HTTP:** `GET` | **Requiere Auth:** No
- **Parámetros que se necesitan pasar:**
  - `user` *(Requerido)*: Nombre de usuario de Last.fm.
  - `from` *(Opcional)*: Timestamp UNIX de inicio del chart (obtenido de `user.getWeeklyChartList`).
  - `to` *(Opcional)*: Timestamp UNIX de fin del chart (obtenido de `user.getWeeklyChartList`).
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `weeklyartistchart` o `weeklyartistchart.artist`):**
  - `@attr`: `{"user": "...", "from": "1212321600", "to": "1212926400"}`.
  - `artist`: Array de objetos con:
    - `@attr.rank`: Posición en el ranking semanal.
    - `name`: Nombre del artista.
    - `mbid`: MusicBrainz ID del artista.
    - `playcount`: Reproducciones del artista en esa semana.
    - `url`: URL del artista en Last.fm.

#### 11. `user.getWeeklyChartList`
Obtiene la lista de todos los charts semanales disponibles para un usuario, expresados como rangos de fechas (`from` y `to`) que luego se envían a los servicios `user.getWeeklyTrackChart`, `user.getWeeklyAlbumChart` o `user.getWeeklyArtistChart`.
- **Verbo HTTP:** `GET` | **Requiere Auth:** No
- **Parámetros que se necesitan pasar:**
  - `user` *(Requerido)*: Nombre de usuario de Last.fm.
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `weeklychartlist.chart`):**
  - `@attr.user`: Nombre del usuario.
  - `chart`: Array de elementos donde cada rango contiene `"from"` y `"to"` en formato UNIX timestamp (ej. `{"#text": "", "from": "1108296002", "to": "1108900802"}`).

#### 12. `user.getWeeklyTrackChart`
Obtiene el chart de canciones de un usuario para un rango de fechas específico. Si no se envían `from` y `to`, devuelve el chart semanal más reciente del usuario.
- **Verbo HTTP:** `GET` | **Requiere Auth:** No
- **Parámetros que se necesitan pasar:**
  - `user` *(Requerido)*: Nombre de usuario de Last.fm.
  - `from` *(Opcional)*: Timestamp UNIX de inicio del chart (obtenido de `user.getWeeklyChartList`).
  - `to` *(Opcional)*: Timestamp UNIX de fin del chart (obtenido de `user.getWeeklyChartList`).
  - `api_key` *(Requerido)*.
- **Cómo devuelve la información (`dataKey`: `weeklytrackchart` o `weeklytrackchart.track`):**
  - `@attr`: `{"user": "...", "from": "1212321600", "to": "1212926400"}`.
  - `track`: Array de objetos con:
    - `@attr.rank`: Posición de la canción en el ranking semanal (`"1"`, `"2"`, ...).
    - `artist`: `{"mbid": "...", "#text": "Nombre del artista"}` *(usa `#text` porque `mbid` es atributo del nodo `<artist>`)*.
    - `name`: Nombre de la canción.
    - `mbid`: MusicBrainz ID de la canción.
    - `playcount`: Reproducciones de la canción en ese rango semanal.
    - `image`: Array de imágenes por `size`.
    - `url`: URL de la canción en Last.fm.
