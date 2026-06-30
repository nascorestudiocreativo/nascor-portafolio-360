<?php
/**
 * Plugin Name: Portafolio 360
 * Description: Muestra un portafolio interactivo estilo carrusel 3D cilíndrico. Alta optimización de carga (AVIF On-The-Fly, Srcset y caché).
 * Version: 2.3.0
 * Author: Nascor
 * Requires PHP: 8.3
 */

if (!defined('ABSPATH')) {
    exit; // Seguridad: Evitar acceso directo
}

class Portafolio_360_Plugin {

    private string $transient_key = 'portafolio_360_html_cache';

    public function __construct() {
        add_action('init', [$this, 'registrar_cpt_portafolio']);
        add_action('add_meta_boxes', [$this, 'agregar_meta_boxes']);
        add_action('save_post_portafolio_360', [$this, 'guardar_meta_boxes']);
        
        // Limpiar caché cuando se borre o cambie de estado un proyecto
        add_action('transition_post_status', [$this, 'limpiar_cache_al_transicionar'], 10, 3);

        add_shortcode('portafolio_360', [$this, 'renderizar_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'registrar_assets']);
    }

    /**
     * Motor de Generación AVIF On-The-Fly
     */
    private function generar_avif($image_url, $target_width) {
        if (empty($image_url)) return '';

        $upload_dir = wp_upload_dir();
        $base_url = $upload_dir['baseurl'];
        $base_dir = $upload_dir['basedir'];

        // Si es una URL externa (ej. placeholder o un link de otra web), no la procesamos
        if (strpos($image_url, $base_url) === false) {
            return $image_url; 
        }

        $relative_path = str_replace($base_url, '', $image_url);
        $original_file = $base_dir . $relative_path;

        if (!file_exists($original_file)) {
            return $image_url;
        }

        $path_parts = pathinfo($original_file);
        $new_filename = $path_parts['filename'] . '-' . $target_width . 'w.avif';
        $new_file_path = $path_parts['dirname'] . '/' . $new_filename;
        $new_file_url = str_replace($base_dir, $base_url, $new_file_path);

        if (file_exists($new_file_path)) {
            return $new_file_url;
        }

        $editor = wp_get_image_editor($original_file);
        if (!is_wp_error($editor)) {
            $editor->resize($target_width, null, false); 
            $editor->set_quality(80); 
            $saved = $editor->save($new_file_path, 'image/avif');
            if (!is_wp_error($saved)) {
                return $new_file_url;
            }
        }

        return $image_url;
    }

    public function registrar_cpt_portafolio(): void {
        $labels = [
            'name'                  => 'Portafolio 360',
            'singular_name'         => 'Proyecto',
            'menu_name'             => 'Portafolio 360',
            'add_new'               => 'Añadir Nuevo',
            'add_new_item'          => 'Añadir Nuevo Proyecto',
            'edit_item'             => 'Editar Proyecto',
            'all_items'             => 'Todos los Proyectos',
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'menu_icon'          => 'dashicons-images-alt2',
            'supports'           => ['title', 'editor', 'thumbnail'],
            'has_archive'        => false,
        ];

        register_post_type('portafolio_360', $args);
    }

    public function agregar_meta_boxes(): void {
        add_meta_box(
            'portafolio_360_detalles',
            'Detalles del Proyecto y Redes',
            [$this, 'renderizar_meta_boxes'],
            'portafolio_360',
            'normal',
            'high'
        );
    }

    public function renderizar_meta_boxes(WP_Post $post): void {
        wp_nonce_field('guardar_portafolio_360', 'portafolio_360_nonce');

        $enlace = get_post_meta($post->ID, '_portafolio_enlace', true);
        $dificultades = get_post_meta($post->ID, '_portafolio_dificultades', true);
        $redes = get_post_meta($post->ID, '_portafolio_redes', true) ?: [];

        echo '<div style="display:flex; flex-direction:column; gap:15px;">';
        
        echo '<div>';
        echo '<label style="font-weight:bold; display:block;">Enlace del Proyecto (URL):</label>';
        echo '<input type="url" name="portafolio_enlace" value="' . esc_attr($enlace) . '" style="width:100%;" placeholder="https://..." />';
        echo '</div>';

        echo '<div>';
        echo '<label style="font-weight:bold; display:block;">Dificultades y Soluciones del Proyecto:</label>';
        echo '<textarea name="portafolio_dificultades" rows="4" style="width:100%;">' . esc_textarea($dificultades) . '</textarea>';
        echo '</div>';

        echo '<hr>';
        echo '<h4>Redes Sociales del Proyecto</h4>';
        for ($i = 0; $i < 3; $i++) {
            $icono = $redes[$i]['icono'] ?? '';
            $url = $redes[$i]['url'] ?? '';
            echo '<div style="display:flex; gap:10px; margin-bottom:10px;">';
            echo '<input type="url" name="portafolio_redes['.$i.'][icono]" value="' . esc_attr($icono) . '" placeholder="URL de la imagen del ícono" style="width:50%;" />';
            echo '<input type="url" name="portafolio_redes['.$i.'][url]" value="' . esc_attr($url) . '" placeholder="Enlace de la red social" style="width:50%;" />';
            echo '</div>';
        }

        echo '</div>';
    }

    public function guardar_meta_boxes(int $post_id): void {
        if (!isset($_POST['portafolio_360_nonce']) || !wp_verify_nonce($_POST['portafolio_360_nonce'], 'guardar_portafolio_360')) { return; }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) { return; }
        if (!current_user_can('edit_post', $post_id)) { return; }

        if (isset($_POST['portafolio_enlace'])) {
            update_post_meta($post_id, '_portafolio_enlace', esc_url_raw($_POST['portafolio_enlace']));
        }
        if (isset($_POST['portafolio_dificultades'])) {
            update_post_meta($post_id, '_portafolio_dificultades', sanitize_textarea_field($_POST['portafolio_dificultades']));
        }
        if (isset($_POST['portafolio_redes']) && is_array($_POST['portafolio_redes'])) {
            $redes_limpias = [];
            foreach ($_POST['portafolio_redes'] as $red) {
                if (!empty($red['url'])) {
                    $redes_limpias[] = [
                        'icono' => esc_url_raw($red['icono']),
                        'url'   => esc_url_raw($red['url'])
                    ];
                }
            }
            update_post_meta($post_id, '_portafolio_redes', $redes_limpias);
        }

        delete_transient($this->transient_key);
    }

    public function limpiar_cache_al_transicionar($new_status, $old_status, $post): void {
        if ($post->post_type === 'portafolio_360') {
            delete_transient($this->transient_key);
        }
    }

    public function registrar_assets(): void {
        wp_register_style('portafolio-360-css', false);
        wp_register_script('portafolio-360-js', false, [], false, true);
    }

    public function renderizar_shortcode(array|string $atts): string {
        wp_enqueue_style('portafolio-360-css');
        wp_add_inline_style('portafolio-360-css', $this->obtener_css());
        
        wp_enqueue_script('portafolio-360-js');
        wp_add_inline_script('portafolio-360-js', $this->obtener_js());

        $output_cacheado = get_transient($this->transient_key);
        if (false !== $output_cacheado) {
            return $output_cacheado;
        }

        $query = new WP_Query([
            'post_type'      => 'portafolio_360',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'ASC',
            'no_found_rows'  => true,
            'update_post_term_cache' => false
        ]);

        if (!$query->have_posts()) {
            return '<p>No hay proyectos en el portafolio aún.</p>';
        }

        $proyectos_json = [];
        $html_items = '';
        $contador_lcp = 0; // Para dar prioridad solo a las primeras imágenes

        while ($query->have_posts()) {
            $query->the_post();
            $id = get_the_ID();
            $titulo = get_the_title();
            
            // OPTIMIZACIÓN DE IMÁGENES: Obtener la imagen base full
            $img_id = get_post_thumbnail_id($id);
            $imagen_orig = wp_get_attachment_image_url($img_id, 'full') ?: 'https://via.placeholder.com/800x600?text=Proyecto';
            
            // Generar AVIFs para el carrusel (tamaños pequeños)
            $img_car_400 = $this->generar_avif($imagen_orig, 400);
            $img_car_800 = $this->generar_avif($imagen_orig, 800); // Retina carrusel

            // Generar AVIFs para el panel de detalles (tamaños grandes)
            $img_det_600 = $this->generar_avif($imagen_orig, 600);
            $img_det_1200 = $this->generar_avif($imagen_orig, 1200);
            
            $enlace = get_post_meta($id, '_portafolio_enlace', true);
            $dificultades = get_post_meta($id, '_portafolio_dificultades', true);
            $redes_crudas = get_post_meta($id, '_portafolio_redes', true) ?: [];

            // Procesar Redes para generar iconos AVIF
            $redes_optimizadas = [];
            foreach ($redes_crudas as $red) {
                $icono_40 = $this->generar_avif($red['icono'], 40);
                $icono_80 = $this->generar_avif($red['icono'], 80);
                $redes_optimizadas[] = [
                    'url' => $red['url'],
                    'icono_src' => $icono_40,
                    'icono_srcset' => $icono_40 . ' 40w, ' . $icono_80 . ' 80w'
                ];
            }

            // Alimentar el JSON que consumirá JavaScript
            $proyectos_json[] = [
                'id'           => $id,
                'titulo'       => $titulo,
                'descripcion'  => apply_filters('the_content', get_the_content()),
                'imagen_src'   => $img_det_600,
                'imagen_srcset'=> $img_det_600 . ' 600w, ' . $img_det_1200 . ' 1200w',
                'enlace'       => $enlace,
                'dificultades' => nl2br(esc_html($dificultades)),
                'redes'        => $redes_optimizadas
            ];

            // Atributo de carga: las primeras 3 (al frente del cilindro) cargan rápido, el resto lazy
            $loading_attr = ($contador_lcp < 3) ? 'fetchpriority="high"' : 'loading="lazy"';
            
            // HTML del ítem con <img> nativo en vez de background-image
            $html_items .= sprintf(
                '<div class="p360-item" data-id="%d">
                    <img src="%s" srcset="%s 400w, %s 800w" sizes="(max-width: 768px) 240px, 320px" width="300" height="180" alt="%s" %s decoding="async">
                </div>',
                $id, esc_url($img_car_400), esc_url($img_car_400), esc_url($img_car_800), esc_attr($titulo), $loading_attr
            );
            
            $contador_lcp++;
        }
        wp_reset_postdata();

        $json_data = htmlspecialchars(json_encode($proyectos_json), ENT_QUOTES, 'UTF-8');

        ob_start();
        ?>
        <div class="portafolio-360-container" data-proyectos="<?php echo $json_data; ?>">
            <div class="p360-escenario">
                <div class="p360-carrusel">
                    <?php echo $html_items; ?>
                </div>
                <button class="p360-nav p360-prev">&#10094;</button>
                <button class="p360-nav p360-next">&#10095;</button>
            </div>

            <div class="p360-detalles" style="display: none;">
                <button class="p360-cerrar">&times;</button>
                
                <div class="p360-top-section">
                    <div class="p360-texto-izq">
                        <h2 class="p360-titulo"></h2>
                        <div class="p360-desc"></div>
                        <a href="#" class="p360-enlace-btn" target="_blank" rel="noopener noreferrer">Ver Proyecto</a>
                    </div>
                    <div class="p360-img-der">
                        <img src="" srcset="" sizes="(max-width: 768px) 100vw, 50vw" alt="Portada del proyecto" loading="lazy" decoding="async" />
                    </div>
                </div>

                <div class="p360-redes-carrusel">
                    <h4>Redes Sociales</h4>
                    <div class="p360-redes-iconos"></div>
                </div>

                <div class="p360-dificultades">
                    <h4>Retos y Soluciones</h4>
                    <p></p>
                </div>
            </div>
        </div>
        <?php
        
        $output_final = ob_get_clean();
        set_transient($this->transient_key, $output_final, 30 * DAY_IN_SECONDS);

        return $output_final;
    }

    private function obtener_css(): string {
        return "
        @import url('https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;700&family=Fraunces:wght@500;700&display=swap');

        .portafolio-360-container { width: 100%; max-width: 1200px; margin: 0 auto; overflow: hidden; padding: 40px 0; }
        
        /* ESCENARIO Y CARRUSEL */
        .p360-escenario { position: relative; height: 450px; perspective: 1200px; display: flex; justify-content: center; align-items: center; margin-bottom: 20px; }
        .p360-carrusel { position: relative; width: 300px; height: 180px; transform-style: preserve-3d; transition: transform 0.8s cubic-bezier(0.25, 0.8, 0.25, 1); }
        .p360-item { 
            position: absolute; 
            width: 300px; height: 180px; 
            cursor: pointer; transition: opacity 0.3s ease, transform 0.3s ease; 
            -webkit-box-reflect: below 5px linear-gradient(transparent, transparent, rgba(0,0,0,0.3));
        }
        
        /* La nueva imagen que reemplaza al background-image */
        .p360-item img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.6);
            background-color: transparent;
        }
        .p360-item:hover img { filter: brightness(1.15); }
        
        /* DISEÑO DE BOTONES */
        .portafolio-360-container .p360-nav, 
        .portafolio-360-container .p360-enlace-btn { 
            color: #FFFFFF !important;
            border-width: 0px !important;
            border-radius: 10px;
            font-size: 18px;
            font-family: 'Figtree', Helvetica, Arial, Lucida, sans-serif !important;
            background-color: #9966cc;
            display: inline-block;
            text-decoration: none;
            transition: 0.3s;
            text-align: center;
            padding: 10px 20px;
            cursor: pointer;
            z-index: 100;
        }
        .portafolio-360-container .p360-nav {
            position: absolute; top: 50%; transform: translateY(-50%);
            padding: 15px 20px;
            font-size: 24px;
        }
        .portafolio-360-container .p360-prev { left: 5%; }
        .portafolio-360-container .p360-next { right: 5%; }

        .portafolio-360-container .p360-nav:hover, 
        .portafolio-360-container .p360-enlace-btn:hover { 
            background-color: #6699ff !important;
        }

        /* DETALLES */
        .p360-detalles { position: relative; background: #f9f9f9; padding: 40px 30px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); animation: fadeIn 0.5s ease; margin-top: 20px;}
        .p360-cerrar { position: absolute; top: 15px; right: 20px; background: transparent; border: none; font-size: 28px; color: #555; cursor: pointer; transition: 0.2s; }
        .p360-cerrar:hover { color: #d00; }
        .p360-top-section { display: flex; flex-wrap: wrap; gap: 30px; margin-bottom: 30px; }
        .p360-texto-izq { flex: 1; min-width: 300px; }
        .p360-img-der { flex: 1; min-width: 300px; display: flex; justify-content: center; align-items: center; }
        .p360-img-der img { max-width: 100%; height: auto; border-radius: 10px; box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .p360-redes-carrusel { text-align: center; margin-bottom: 30px; padding: 20px 0; border-top: 1px solid #ddd; border-bottom: 1px solid #ddd; }
        .p360-redes-iconos { display: flex; justify-content: center; gap: 20px; }
        .p360-redes-iconos img { width: 40px; height: 40px; transition: 0.3s; object-fit: contain; }
        .p360-redes-iconos img:hover { transform: translateY(-5px); }
        .p360-dificultades { text-align: center; max-width: 800px; margin: 0 auto;}

        /* DISEÑO DE TÍTULOS */
        .portafolio-360-container h1, 
        .portafolio-360-container h2, 
        .portafolio-360-container h3, 
        .portafolio-360-container h4, 
        .portafolio-360-container h5,
        .portafolio-360-container .p360-titulo {
            background: linear-gradient(90deg, #003366, #cc99cc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            color: transparent; 
            font-family: 'Fraunces', Georgia, 'Times New Roman', serif;
            font-weight: 500;
            font-size: 2vw;
            line-height: 1.1em;
            margin-top: 0;
            margin-bottom: 15px;
        }

        .portafolio-360-container,
        .portafolio-360-container p,
        .portafolio-360-container div,
        .portafolio-360-container span {
            line-height: 1.4em;
            font-family: 'Figtree', Helvetica, Arial, Lucida, sans-serif;
            font-size: 16px;
            margin-bottom: 20px;
            color: #003366;
        }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        @media (max-width: 768px) {
            .p360-escenario { height: 350px; }
            .p360-carrusel, .p360-item { width: 220px; height: 130px; }
            
            .p360-top-section { 
                flex-direction: column; 
                align-items: center; 
                text-align: center; 
                gap: 20px; 
            }
            .p360-texto-izq, .p360-img-der { 
                width: 100%; 
                min-width: unset; 
            }
            .p360-img-der img { 
                width: 100%; 
                max-width: 350px; 
            }

            .portafolio-360-container h1, .portafolio-360-container h2, 
            .portafolio-360-container h3, .portafolio-360-container h4, 
            .portafolio-360-container h5, .portafolio-360-container .p360-titulo {
                font-size: 26px;
            }
        }
        ";
    }

    private function obtener_js(): string {
        return '
        document.addEventListener("DOMContentLoaded", function() {
            const containers = document.querySelectorAll(".portafolio-360-container");
            
            containers.forEach(container => {
                const dataRaw = container.getAttribute("data-proyectos");
                if (!dataRaw) return;
                
                const proyectos = JSON.parse(dataRaw);
                const carrusel = container.querySelector(".p360-carrusel");
                const items = container.querySelectorAll(".p360-item");
                const btnPrev = container.querySelector(".p360-prev");
                const btnNext = container.querySelector(".p360-next");
                const panelDetalles = container.querySelector(".p360-detalles");
                const btnCerrar = container.querySelector(".p360-cerrar");
                
                const elTitulo = container.querySelector(".p360-titulo");
                const elDesc = container.querySelector(".p360-desc");
                const elImg = container.querySelector(".p360-img-der img");
                const elBtnLink = container.querySelector(".p360-enlace-btn");
                const elRedes = container.querySelector(".p360-redes-iconos");
                const elDificultades = container.querySelector(".p360-dificultades p");

                let anguloActual = 0;
                const totalItems = items.length;
                const anguloPorItem = totalItems > 0 ? 360 / totalItems : 0;
                
                const anchoItem = window.innerWidth < 768 ? 240 : 320; 
                const radio = totalItems <= 1 ? 0 : Math.max(anchoItem, (totalItems * anchoItem) / (2 * Math.PI));

                items.forEach((item, index) => {
                    const angulo = index * anguloPorItem;
                    item.style.transform = `rotateY(${angulo}deg) translateZ(${radio}px)`;
                    
                    item.addEventListener("click", () => {
                        anguloActual = -angulo;
                        actualizarCarrusel();
                        mostrarDetalles(proyectos.find(p => p.id == item.getAttribute("data-id")));
                    });
                });

                function actualizarCarrusel() {
                    carrusel.style.transform = `rotateY(${anguloActual}deg)`;
                }

                btnNext.addEventListener("click", () => {
                    anguloActual -= anguloPorItem;
                    actualizarCarrusel();
                });

                btnPrev.addEventListener("click", () => {
                    anguloActual += anguloPorItem;
                    actualizarCarrusel();
                });

                btnCerrar.addEventListener("click", () => {
                    panelDetalles.style.display = "none";
                });

                function mostrarDetalles(proyecto) {
                    if(!proyecto) return;
                    
                    panelDetalles.style.display = "block";
                    panelDetalles.style.animation = "none";
                    setTimeout(() => panelDetalles.style.animation = "", 10);

                    elTitulo.textContent = proyecto.titulo;
                    elDesc.innerHTML = proyecto.descripcion;
                    
                    // Inyectar datos srcset e imagen correctos optimizados
                    elImg.src = proyecto.imagen_src;
                    elImg.srcset = proyecto.imagen_srcset;
                    
                    if (proyecto.enlace) {
                        elBtnLink.href = proyecto.enlace;
                        elBtnLink.style.display = "inline-block";
                    } else {
                        elBtnLink.style.display = "none";
                    }

                    elDificultades.innerHTML = proyecto.dificultades;

                    elRedes.innerHTML = "";
                    if (proyecto.redes && proyecto.redes.length > 0) {
                        proyecto.redes.forEach(red => {
                            if(red.icono_src && red.url) {
                                const a = document.createElement("a");
                                a.href = red.url;
                                a.target = "_blank";
                                a.rel = "noopener noreferrer";
                                
                                const img = document.createElement("img");
                                img.src = red.icono_src;
                                img.srcset = red.icono_srcset;
                                img.sizes = "40px";
                                img.width = 40;
                                img.height = 40;
                                img.alt = "Red Social";
                                img.loading = "lazy";
                                img.decoding = "async";
                                
                                a.appendChild(img);
                                elRedes.appendChild(a);
                            }
                        });
                    } else {
                        elRedes.innerHTML = "<p style=\'font-size:12px;color:#888;\'>No hay redes asociadas.</p>";
                    }
                }
            });
        });
        ';
    }
}

new Portafolio_360_Plugin();