<?php
/**
* Plugin Name: Form-Page-View
**/

// La interfaz del plugin debe tener:
    // - Un select de los usuarios. Para escoger al usuario que se le mostrará la página.
    //---------------------- si el usuario escogido está conectado al wordpress panel -------------
    // - un botón a la página creada dinamicamente (esta pagina no está visibile en la lista de paginas del sitio)


// Cada vez que se cargue una pagina, busque si existe algún formulario
// si existe algún formulario, le va a crear un lissener para el botón de envio.
// Si alguíen envía la información del formulario, se hace una query a la DB para retribuir la información y colocarla en una página especifica.

// Seguridad
if (!defined ('ABSPATH')){
    echo 'No puedo hacer nada cuando me llaman directamente :(';
    die;
}

// Cuando se activa el plugin crea-registra un tabla nueva en la base de datos
register_activation_hook(__FILE__, 'database_creation');

function database_creation() {
    // Crear la tabla
    global $wpdb;

    $table_name = $wpdb->prefix . 'tabla_pagina_unica';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (

        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name tinytext NOT NULL,
        data LONGTEXT NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    error_log('El plugin ha sido activado.');
}

// Cada vez que se abra una pagina, revisar si hay un formulario y anexarlo con la base de datos.

function detectar_envio_formulario() {
    ?>
    <script>


        document.addEventListener("DOMContentLoaded", function () {

            let _all_forms_send_btn = document.querySelectorAll("form input[type=submit]");

            _all_forms_send_btn.forEach(_form_send_btn => {
                _form_send_btn.addEventListener("click", async function(e){
                    e.preventDefault(); // Evita que el formulario se envíe y recargue la página
                    
                    // formulario del objeto _form
                    let _form_element = _form_send_btn.closest("form");
                    // Prevenir el evento de submit
                    let formData = new FormData(_form_element); 

                    formData.append("action", "capturar_formulario");
                            
                    try {
                        let response = await fetch("<?php echo admin_url('admin-ajax.php'); ?>", {
                            method: "POST",
                            body: formData,
                        });

                        let data = await response.json();
                        console.log("Formulario capturado:", data);

                        if (data.success) {
                            alert("Formulario enviado con éxito.");
                            _form_element.reset(); // Opcional: limpiar formulario
                        } else {
                            alert("Error: " + data.message);
                        }

                    } catch (error) {
                        console.error("Error al capturar el formulario:", error);
                    }
                       

                });
            });

        });




    </script>
    <?php
}
add_action('wp_footer', 'detectar_envio_formulario');

//Guardamos los Datos en la Base de Datos con PHP
function capturar_formulario() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'tabla_pagina_unica';

    // Excluyo los datos innecesarios

    $excluir = ['action','submit'];
    
    // filtro los datos del formulario
    $form_data = array_filter($_POST, function($key) use ($excluir){
        return !in_array($key, $excluir);
    }, ARRAY_FILTER_USE_KEY);

    // Sanitizar valores
    $form_data = array_map('sanitize_text_field', $form_data);

    if (!empty($form_data)) {
        $wpdb->insert(
            $table_name,
            array(
                'name' => sanitize_text_field($form_data['nombre'] ?? 'Sin nombre'), // Puedes usar un campo clave
                'data' => json_encode($form_data, JSON_UNESCAPED_UNICODE), // Guarda el JSON
            ),
            array('%s', '%s')
        );

        wp_send_json_success(['message' => 'Formulario capturado y almacenado.', 'data' => $form_data]);
    } else {
        wp_send_json_error(['message' => 'No se recibió información válida.']);
    }

}
add_action('wp_ajax_capturar_formulario', 'capturar_formulario');
add_action('wp_ajax_nopriv_capturar_formulario', 'capturar_formulario');