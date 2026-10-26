# Afgrantec

Sitio web corporativo de **Afgrantec**, empresa especializada en el diseño, suministro y soporte técnico de equipo para avicultura y porcicultura en Latinoamérica y el Caribe.

El sitio es una página estática de una sola sección, con contenido en español y estado actual de *en construcción* (indicado en el hero).

- **Contacto:** sebastian.lora@afgrantec.com
- **Stack:** Astro 5 · Tailwind CSS 4 · TypeScript 5
- **Idioma:** español (`es`)

---

## Índice

- [Tecnologías](#tecnologías)
- [Requisitos previos](#requisitos-previos)
- [Instalación](#instalación)
- [Scripts disponibles](#scripts-disponibles)
- [Estructura del proyecto](#estructura-del-proyecto)
- [Contenido central](#contenido-central)
- [Flujo de trabajo](#flujo-de-trabajo)
- [Despliegue](#despliegue)
- [Licencia](#licencia)
- [Contacto](#contacto)

---

## Tecnologías

| Tecnología | Versión | Propósito |
|---|---|---|
| [Astro](https://astro.build) | 7.x | Framework y generador de sitio estático |
| [Tailwind CSS](https://tailwindcss.com) | 4.x | Utilidades de estilo (vía `@tailwindcss/vite`) |
| [TypeScript](https://www.typescriptlang.org) | 6.x | Tipado en datos y configuración |
| [pnpm](https://pnpm.io) | 12.4.2 | Gestor de paquetes |

---

## Requisitos previos

- **Node.js** 18.17.1 o superior (se recomienda la versión LTS actual).
- **pnpm** 12.4.2. Si aún no lo tienes, actívalo con:

  ```bash
  corepack enable
  ```

  o instálalo con `npm install -g pnpm`.

---

## Instalación

1. Clonar el repositorio e instalar dependencias:

   ```bash
   pnpm install
   ```

2. Levantar el servidor de desarrollo:

   ```bash
   pnpm dev
   ```

El sitio quedará disponible en **http://localhost:4321**.

---

## Scripts disponibles

| Comando | Descripción |
|---|---|
| `pnpm dev` | Inicia el servidor de desarrollo (`astro dev`) |
| `pnpm start` | Equivalente a `pnpm dev` |
| `pnpm build` | Genera el sitio estático en `dist/` |
| `pnpm preview` | Previsualiza el build en local (`astro preview`) |
| `pnpm check` | Verifica tipos y errores de Astro (`astro check`) |
| `pnpm astro` | Ejecuta la CLI de Astro |

---

## Estructura del proyecto

```
afgrantec/
├── astro.config.mjs       Configuración de Astro + plugin de Tailwind
├── package.json           Dependencias y scripts
├── tsconfig.json          Configuración de TypeScript
├── pnpm-lock.yaml         Versiones exactas de dependencias
├── src/
│   ├── pages/
│   │   └── index.astro    Página principal (única página del sitio)
│   ├── layouts/
│   │   └── Layout.astro   Plantilla base: html, head, Header y Footer
│   ├── components/
│   │   ├── Header.astro   Cabecera fija con logo y correo de contacto
│   │   └── Footer.astro   Pie de página
│   ├── data/
│   │   └── site.ts        Contenido y datos del sitio
│   └── styles/
│       └── global.css     Estilos globales y tokens de Tailwind
└── dist/                  Salida del build (generada, no editar)
```

---

## Contenido central

Todo el contenido editable del sitio vive en **`src/data/site.ts`**:

| Variable | Descripción |
|---|---|
| `site.name` / `site.title` / `site.description` | Nombre y metadatos SEO del sitio |
| `email` | Correo de contacto usado en header, hero y footer |
| `services[]` | Lista de servicios (número, título y texto) |
| `countries[]` | Países de cobertura: México, Colombia, Argentina |

Servicios actuales:

1. **Ingeniería y diseño** — Revisión de la instalación y definición del equipo necesario.
2. **Instalación y capacitación** — Instalación, puesta en marcha y capacitación del personal.
3. **Suministro e importación** — Cotización, suministro y coordinación de la importación.
4. **Repuestos y postventa** — Repuestos, soporte técnico y gestión de garantías.

Para modificar textos, servicios o países, basta con editar este archivo.

---

## Flujo de trabajo

1. Editar el contenido en `src/data/site.ts`.
2. Ajustar estructura o estilo en `src/pages/*.astro` y los componentes.
3. Ver los cambios en tiempo real con `pnpm dev`.
4. Verificar tipos y errores con `pnpm check`.
5. Compilar con `pnpm build`.
6. Probar el resultado con `pnpm preview`.

---

## Despliegue

El build genera archivos estáticos en `dist/`, por lo que el sitio puede publicarse en cualquier hosting estático (Netlify, Vercel, Cloudflare Pages, etc.) con esta configuración:

| Ajuste | Valor |
|---|---|
| Comando de build | `pnpm build` |
| Directorio de salida | `dist/` |

---

## Licencia

Todos los derechos reservados. Este proyecto es privado y no está sujeto a una licencia de código abierto.

El código, el diseño y los contenidos de este sitio no pueden ser reproducidos, distribuidos ni utilizados con fines comerciales sin autorización expresa por escrito de Afgrantec.

© 2026 Afgrantec. Todos los derechos reservados.

---

## Contacto

Para consultas sobre el proyecto o sobre los servicios de Afgrantec:

- **Correo:** [sebastian.lora@afgrantec.com](mailto:sebastian.lora@afgrantec.com)
