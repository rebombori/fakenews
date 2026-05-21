# FakeNews — Juego de detección de noticias falsas

Aplicación web para campañas de sensibilización contra la desinformación.
Los jugadores deben identificar qué noticias son reales y cuáles falsas.
Al final pueden dejar su email para participar en un sorteo entre los mejores.

**Demo:** Desplegado en [quinrebombori.es/fakenews](http://quinrebombori.es/fakenews/)

---

## Requisitos del servidor

| Requisito | Detalle |
|---|---|
| PHP | 8.0 o superior |
| Extensiones PHP | `pdo_sqlite`, `curl`, `json`, `session` (todas incluidas en PHP estándar) |
| Servidor web | Apache con `mod_rewrite` habilitado, o Nginx con configuración equivalente |
| Permisos | El directorio `data/` debe tener permisos de escritura para el proceso del servidor web (`www-data` en Apache/Nginx) |
| Base de datos | **Sin MySQL.** Usa SQLite — un archivo `.db` que se crea automáticamente |
| Composer | **No necesario.** Sin dependencias externas |

---

## Instalación en 5 pasos

### 1. Subir los archivos

Sube el contenido de esta carpeta a un directorio de tu servidor, por ejemplo `/var/www/html/fakenews/` o la raíz del dominio.

### 2. Dar permisos de escritura a `data/`

```bash
chmod 755 data/
```

Si el servidor corre como `www-data`:

```bash
chown -R www-data:www-data data/
```

### 3. Crear el archivo de configuración

```bash
cp .env.example .env
```

Editar `.env` con un editor de texto:

```dotenv
APP_DEBUG=0
APP_SESSION_NAME="fakenews_session"
APP_TIMEZONE="Europe/Madrid"

# Contraseña del panel de administración (cámbiala por una segura)
ADMIN_PASSWORD="elige_una_contrasena_segura"

# Configuración SMTP para enviar emails de confirmación
SMTP_HOST="smtp.tuproveedor.com"
SMTP_PORT=587
SMTP_USER="campana@tuong.org"
SMTP_PASS="tu_contrasena_smtp"
SMTP_FROM="campana@tuong.org"
SMTP_FROM_NAME="Nombre de la ONG"
```

> **Nota SMTP:** Si usas Gmail, necesitas crear una "Contraseña de aplicación" en la configuración de seguridad de tu cuenta Google (Autenticación en dos pasos → Contraseñas de aplicación).

### 4. Verificar que Apache tiene `mod_rewrite` habilitado

```bash
a2enmod rewrite
service apache2 restart
```

Asegúrate de que el `VirtualHost` tiene `AllowOverride All`.

### 5. Abrir en el navegador

Visita `https://tudominio.org/` — la aplicación se inicializa automáticamente y la base de datos SQLite se crea sola en el primer acceso.

---

## Panel de administración

El panel **no tiene ningún enlace público**. Solo quienes conozcan la URL pueden acceder.

**URL:** `https://tudominio.org/admin/`

**Contraseña:** la que hayas configurado en `ADMIN_PASSWORD` del `.env`.

### ¿Qué puedes hacer desde el panel?

| Sección | Descripción |
|---|---|
| **Dashboard** | Estadísticas de visitas, partidas completadas, precisión media, países, dispositivos |
| **Participantes** | Lista de emails con puntuaciones, filtrable y exportable a CSV (para gestionar el sorteo) |
| **Configuración** | Editar el texto del informe, el enlace de campaña, los textos legales y las plantillas de email — **sin tocar código** |

---

## Configurar el contenido de la campaña

Si prefieres editar el contenido directamente (sin usar el panel), edita `config/campaign.yaml`:

```yaml
total_cards: 10       # Noticias por partida
min_real: 3           # Mínimo de noticias reales (el resto serán falsas, de forma aleatoria)

report:
  es: "Texto del informe en español..."
  val: "Text en valencià..."
  en: "Text in English..."

campaign_link: "https://tuong.org/campana"
```

---

## Añadir o actualizar noticias

Las noticias están en `data/news/` como archivos JSON, uno por noticia.

Estructura mínima de cada archivo:

```json
{
  "id": "noticia_unica_001",
  "title": "Titular de la noticia",
  "title_es": "Titular en español (opcional)",
  "title_val": "Titular en valencià (opcional)",
  "title_en": "Headline in English (optional)",
  "summary": "Resumen de la noticia.",
  "source": "Nombre de la fuente",
  "fake": false,
  "source_type": "real",
  "source_reputation": 4,
  "topic": "society",
  "difficulty": "medium"
}
```

- `fake`: `true` para noticias falsas, `false` para reales
- `source_type`: debe ser `"real"` si `fake` es `false`, y `"fake"` si `fake` es `true`
- `source_reputation`: del 1 (baja) al 5 (alta)
- `difficulty`: `"easy"`, `"medium"` o `"hard"`

Para tener 10 noticias por partida necesitas **al menos 10 archivos en total** (combinando reales y falsas).

---

## Gestión del sorteo

Cuando haya que hacer el sorteo:

1. Ir al panel → **Participantes**
2. Usar el filtro "Puntuación mínima" para ver solo los mejores jugadores
3. Pulsar **Exportar CSV**
4. Abrir el CSV en Excel o LibreOffice Calc
5. Seleccionar los ganadores (manualmente o con una fórmula de aleatorio)

---

## Seguridad

- El archivo `.env` nunca debe ser accesible desde el navegador (el `.htaccess` lo bloquea)
- La base de datos `data/fakenews.db` tampoco es accesible desde el navegador
- El panel de admin no tiene ningún enlace público — solo accesible por URL directa
- Las IPs se guardan anonimizadas (solo los primeros 3 octetos)
- No se almacenan contraseñas de usuarios

---

## Idiomas soportados

El juego detecta automáticamente el idioma del navegador:

| Idioma | Código | Detectado desde |
|---|---|---|
| Español | `es` | `es`, `es-ES`, `es-*` |
| Valenciano | `val` | `ca`, `ca-ES`, `ca-Valencia`, `val` |
| Inglés | `en` | `en`, `en-US`, `en-GB`, `en-*` |

El usuario puede cambiarlo manualmente desde el selector en la barra superior.

---

## Soporte técnico

Para dudas técnicas o reportar problemas:
- Abrir un issue en este repositorio: `https://github.com/rebombori/fakenews/issues`
