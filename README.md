# Portafolio 360

Plugin para WordPress que muestra un portafolio interactivo estilo carrusel 3D cilíndrico[cite: 3]. Está enfocado en el alto rendimiento y Core Web Vitals mediante una extrema optimización de carga de recursos e imágenes[cite: 3].

## 🚀 Características Principales

*   **Generación AVIF On-The-Fly:** Motor interno con `WP_Image_Editor` que convierte y redimensiona imágenes a formato AVIF (con compresión al 80%) automáticamente para los carruseles y paneles de detalles[cite: 3].
*   **Carrusel 3D Interactivo:** Experiencia inmersiva mediante transformaciones CSS 3D y eventos de JavaScript para calcular ángulos y mostrar detalles dinámicamente[cite: 3].
*   **Custom Post Type (CPT):** Registra el tipo de post "Portafolio 360" (Proyecto) para una gestión ordenada e independiente dentro del panel de administración[cite: 3].
*   **Meta Boxes Nativos:** Campos dedicados y seguros (con validación `nonce`) para añadir la URL del proyecto, sección de "Dificultades y Soluciones", y hasta 3 redes sociales[cite: 3].
*   **Sistema de Caché Inteligente:** Guarda el HTML generado del shortcode utilizando la API de Transients durante 30 días, eliminando la latencia. La caché se limpia automáticamente al cambiar el estado de cualquier proyecto[cite: 3].
*   **Carga LCP Optimizada:** Utiliza `fetchpriority="high"` en las primeras 3 imágenes frontales del carrusel y `loading="lazy"` en el resto de los elementos[cite: 3].

## 📋 Requisitos del Sistema

*   **PHP:** Versión 8.3 o superior[cite: 3].
*   **WordPress:** Instalación funcional y permisos de escritura en la carpeta de subidas (`wp_upload_dir`) para la generación de imágenes AVIF[cite: 3].

## 🛠️ Instalación y Uso

1. Descarga el repositorio en formato `.zip` o clónalo en tu carpeta `/wp-content/plugins/`.
2. Activa el plugin **Portafolio 360** desde el panel de WordPress[cite: 3].
3. Ve a la nueva pestaña "Portafolio 360" en tu menú lateral y comienza a añadir proyectos[cite: 3].
4. Copia y pega el siguiente shortcode en la página donde desees renderizar la vista 3D:
   `[portafolio_360]`[cite: 3]

## 👨‍💻 Autor y Versión

*   **Autor:** Nascor[cite: 3]
*   **Versión:** 2.3.0[cite: 3]
