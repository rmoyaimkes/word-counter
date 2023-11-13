<?php
/*
Plugin Name: Calculadora de Traducción
Description: Calcula el coste de traducción según el número de palabras y las tarifas configuradas.
Version: 1.0.1
*/

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ob_start();

//require 'vendor/autoload.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpWord\IOFactory as phpdoc;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use setasign\Fpdi\Fpdi;

use PhpOffice\PhpPresentation\IOFactory as powerpoint;

use Smalot\PdfParser\Parser;



add_action( 'wp_ajax_nopriv_limpiar_idiomas', 'limpiar_idiomas' );
add_action( 'wp_ajax_limpiar_idiomas', 'limpiar_idiomas' );


add_filter('plugins_api', 'my_plugin_api_call', 10, 3);

function my_plugin_api_call($res, $action, $args) {
    if ($action === 'plugin_information' && isset($args->slug) && $args->slug === 'word-counter') {
        $res = new stdClass();
        $res->slug = 'word-counter';
        $res->name = 'Calculadora de Traducción';
        $res->version = '1.0.1';
        $res->tested = 'WordPress 6.4.1';
        $res->requires = 'WordPress 6.0.0';
        $res->author = 'IMK';
        $res->download_link = 'https://github.com/rmoyaimkes/word-counter/archive/refs/tags/v1.0.0.zip';
        $res->trunk = 'https://github.com/rmoyaimkes/word-counter/';
        $res->last_updated = '2023-11-13';
        $res->sections = array(
            'description' => 'Calcula el coste de traducción según el número de palabras y las tarifas configuradas.',
            'changelog' => '== Changelog ==\n\n= 1.0.0 =\n* Primera subida de ficheros.',
        );

        return $res;
    }
    return $res;
}





function limpiar_idiomas(){

    $idioma_origen_post=$_POST['idioma_origen'];

    $tarifas_guardadas = get_option('traduccion_calculadora_tarifas', array());

    $idiomas_disponibles=array();

    foreach ($tarifas_guardadas as $key => $tarifa_existente) {
        if ($tarifa_existente['origen'] === $idioma_origen_post && !in_array($tarifa_existente['destino'], $idiomas_disponibles)) {

            array_push($idiomas_disponibles, $tarifa_existente['destino']);
        }
    }

    echo json_encode($idiomas_disponibles);

    // Asegurarse de que no se imprima nada más que la respuesta JSON
    wp_die();
}



function normalizarnombreFichero( $nombre ){
    $string = trim($nombre);
 
    $string = str_replace(
        array('á', 'à', 'ä', 'â', 'ª', 'Á', 'À', 'Â', 'Ä'),
        array('a', 'a', 'a', 'a', 'a', 'A', 'A', 'A', 'A'),
        $string
    );
 
    $string = str_replace(
        array('é', 'è', 'ë', 'ê', 'É', 'È', 'Ê', 'Ë'),
        array('e', 'e', 'e', 'e', 'E', 'E', 'E', 'E'),
        $string
    );
 
    $string = str_replace(
        array('í', 'ì', 'ï', 'î', 'Í', 'Ì', 'Ï', 'Î'),
        array('i', 'i', 'i', 'i', 'I', 'I', 'I', 'I'),
        $string
    );
 
    $string = str_replace(
        array('ó', 'ò', 'ö', 'ô', 'Ó', 'Ò', 'Ö', 'Ô'),
        array('o', 'o', 'o', 'o', 'O', 'O', 'O', 'O'),
        $string
    );
 
    $string = str_replace(
        array('ú', 'ù', 'ü', 'û', 'Ú', 'Ù', 'Û', 'Ü'),
        array('u', 'u', 'u', 'u', 'U', 'U', 'U', 'U'),
        $string
    );
 
    $string = str_replace(
        array('ñ', 'Ñ', 'ç', 'Ç'),
        array('n', 'N', 'c', 'C',),
        $string
    );
 
    

    $string=str_replace(' ', '-', $string);


    return $string;
}

// Función para mostrar el formulario
function traduccion_calculadora_form() {

    //fabri: begin
    //var_dump($_POST);
    $word_counter_msg = null;
    
    $word_counter_msg2="";
    $word_counter_msg.= var_export($_POST, true);
    //fabri: end
    

    // Verificar si el formulario se envió
    if (isset($_POST['calcular_traduccion'])) {
    
        // Obtener los valores del formulario
        $nombre = sanitize_text_field($_POST['nombre']);
        $empresa = sanitize_text_field($_POST['empresa']);
        $email = sanitize_email($_POST['email']);
        $tel = sanitize_text_Field($_POST['tel']);
        $idioma_origen = sanitize_text_field($_POST['idioma_origen']);
        $idioma_destino = sanitize_text_field($_POST['idioma_destino']);
        $archivo = $_FILES['archivo'];
        $nomFicheroNormalizado=normalizarnombreFichero($archivo['name']);

        // Verificar si se proporcionó un archivo
        if ($archivo['error'] === 0) {
            // Leer el contenido del archivo
            $wp_upload_dir = wp_get_upload_dir();
            $basedir = wp_normalize_path(rtrim( $wp_upload_dir['basedir'] ,'\\/'));

            $resultado = move_uploaded_file($archivo['tmp_name'],  $basedir.'/presupuestos/'.$nomFicheroNormalizado);
            
            // Contar las palabras en el archivo (puede necesitar una lógica más sofisticada)
            $numero_palabras = contarPalabrasEnDocumento($basedir.'/presupuestos/'.$nomFicheroNormalizado);
            $numero_palabras = intval($numero_palabras);
            
            // Obtener la tarifa de traducción desde la base de datos
            $tarifa_normal = obtener_tarifa_traduccion($idioma_origen, $idioma_destino,"Normal");

            $tarifa_urgente = obtener_tarifa_traduccion($idioma_origen, $idioma_destino,"Urgente");

           
           
                // Enviar un correo electrónico al administrador con los detalles del cliente
            $admin_email = get_option('admin_email');
            $subject = 'Nueva solicitud de traducción';
            $message = "<p>Nombre:". $nombre."</p>";
            $message .= "<p>Empresa:". $empresa."</p>";
            $message .= "<p>Correo Electrónico:". $email."</p>";
            $message .= "<p>Teléfono:". $tel."</p>";
            $message .= "<p>Idioma origen:". $idioma_origen."</p>";
            $message .= "<p>Idioma destino:". $idioma_destino."</p>";
            $message .= "<p>Número de palabras:". $numero_palabras."</p>";
            if ($tarifa_normal !== false){

                $coste_traduccion_normal = $numero_palabras * $tarifa_normal;
                $message .= "<p>Coste de traducción normal:". $coste_traduccion_normal." €</p>";
            }
            
            if($tarifa_urgente !==false){

                $coste_traduccion_urgente = $numero_palabras * $tarifa_urgente;
                $message .= "<p>Coste de traducción urgente:". $coste_traduccion_urgente." €</p>";
            }
                
                 // Define el archivo adjunto
               // $attachment = array(WP_CONTENT_DIR . '/uploads/presupuestos/'.$archivo['name']);


                $attachment = array($basedir.'/presupuestos/'.$nomFicheroNormalizado);
                //$attachment=array(WP_CONTENT_DIR . '/uploads/file_to_attach.zip');
                 $headers = array('Content-Type: text/html; charset=UTF-8');
                
                // Envía el correo electrónico
                wp_mail('rmoya@imk.es', $subject,$message,$headers, $attachment);
                
                if (isset($_POST['newsletter'])) {
                    // El checkbox ha sido marcado
                    $curl = curl_init();

                    curl_setopt_array($curl, array(
                      CURLOPT_URL => "https://ovstranslations.ipzmarketing.com/api/v1/subscribers",
                      CURLOPT_RETURNTRANSFER => true,
                      CURLOPT_ENCODING => "",
                      CURLOPT_MAXREDIRS => 10,
                      CURLOPT_TIMEOUT => 30,
                      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                      CURLOPT_CUSTOMREQUEST => "POST",
                      CURLOPT_POSTFIELDS => "{\"status\":\"active\",\"email\":\"$email\",\"name\":\"$name\",\"group_ids\":[1]}",
                      CURLOPT_HTTPHEADER => array(
                        "content-type: application/json",
                        "x-auth-token: 8Z7mZcX8a5oLoz9Nsgu_sSWFc3b1pMdnKL92f2kh"
                      ),
                    ));

                    $response = curl_exec($curl);
                    $err = curl_error($curl);

                    curl_close($curl);
                    
                }
                
                //fabri: begin

                $word_counter_msg2.='<p class="alert alert-info">Presupuesto orientativo, sera revisado por Overseas Translations para confirmar el coste </p>';
                $word_counter_msg2.= 'Número de palabras: ' . $numero_palabras . '<br>';

                if ($tarifa_normal !== false){

                    $coste_traduccion_normal = $numero_palabras * $tarifa_normal;
                    $word_counter_msg2 .= "Coste de traducción normal:". $coste_traduccion_normal." €</br>";
                }
            
                if($tarifa_urgente !==false){

                    $coste_traduccion_urgente = $numero_palabras * $tarifa_urgente;
                    $word_counter_msg2 .= "Coste de traducción urgente:". $coste_traduccion_urgente." €</br>";
                }
                
                //fabri: end

        } else {
            //fabri: begin
            //echo 'No se pudo procesar el archivo.';
             $word_counter_msg2.= 'No se pudo procesar el archivo.';
            //fabri: end
        }
      
        //fabri: begin
        //recarga el form vacio para que no se reenvie con F5/reload page
        $_SESSION['word_counter_msg'] = $word_counter_msg;

         $_SESSION['word_counter_msg2'] = $word_counter_msg2;
       
        header('Location: '.$_SERVER['REQUEST_URI']);
        ob_end_flush();
        //fabri: end
    }
    
    //fabri: begin
    else {
        
        if(!empty($_SESSION['word_counter_msg'])) {
            echo($_SESSION['word_counter_msg2']);
            $_SESSION['word_counter_msg']=null;
            $_SESSION['word_counter_msg2']=null;
        }
    }
    //fabri: end
        
    // Mostrar el formulario


    wp_enqueue_script( 'sweet-alerts-frontend-js-imk', 'https://cdn.jsdelivr.net/npm/sweetalert2@11.9.0/dist/sweetalert2.all.min.js');

    wp_enqueue_style( 'sweet-alerts-frontend-css-imk', 'https://cdn.jsdelivr.net/npm/sweetalert2@11.9.0/dist/sweetalert2.min.css');

    wp_enqueue_script('acciones-frontend-js-imk', plugin_dir_url(__FILE__).'include/js/frontend-actions-scripts.js');

    wp_enqueue_style ('estilos-contador-palabras-css',plugin_dir_url(__FILE__).'include/css/style.css');
    

    ?>
    <form id="formulario" method="post" enctype="multipart/form-data">
        <label for="nombre">Nombre:</label>
        <input type="text" id="nombre" name="nombre" required><br>

        <label for="empresa">Nombre de la Empresa:</label>
        <input type="text" name="empresa" required><br>

        <label for="email">Correo Electrónico:</label>
        <input type="email" id="email" name="email" required><br>
        
        <label for="tel">Teléfono:</label>
        <input type="tel" name="tel" required><br>

        <label for="idioma_origen">Idioma de Origen:</label>
        <select name="idioma_origen" id="idioma_origen">
            <?php echo generar_lista_idiomas_org(); ?>
        </select><br>

        <label for="idioma_destino">Idioma de Destino:</label>
        <select name="idioma_destino" id="idioma_destino">
            
        </select><br>

        <label for="archivo">Archivo a Traducir: ( doc, docx, pdf, odt, txt, xls, xlsx, ppt, pptx, pps )</label>
        <input type="file" acept="doc,dox,pdf,odt,txt,xls,xlsx,pptx,ppt,pps" name="archivo" id="archivo" ><br>
        
        <div class="checkbox">
            <div class="checker" id="newsletter">
                <input type="checkbox" value="0"  name="newsletter" autocomplete="off">
                <label for="newsletter">Suscribirme a la newsletter</label>
            </div>
        </div>
        
        <div class="required checkbox">
            <div class="checker" id="politica_privacidad">
                <input type="checkbox" value="0" required  name="politica_privacidad" autocomplete="off">
                <label for="politica_privacidad">Acepto la <a href="/politica-privacidad">política de privacidad</a></label>
            </div>
        </div>
        
        <input type="submit" name="calcular_traduccion" value="Calcular Traducción">

    </form>
    
    <?php
}


function contarPalabrasEnTxt2($archivo) {
    $contenido = file_get_contents($archivo);
    $palabras = str_word_count($contenido);
 
    return $palabras;
}

function contarPalabrasEnTxt($archivo) {
     $contenido = file_get_contents($archivo);
    $contenido = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $contenido); // Reemplaza caracteres no alfabéticos ni números con espacios
    $contenido = preg_replace('/\s+/', ' ', $contenido); // Reemplaza múltiples espacios en blanco con uno solo
    $contenido = trim($contenido); // Elimina espacios en blanco al principio y al final
    $palabras = str_word_count($contenido);
 
    return $palabras;
}



function contarPalabrasEnDoc($source) {
    $word_count = 0;


    // Load the DOC file using PHPWord
     $phpWord = phpdoc::load($source, 'MsDoc');

    // Get all the text elements from the document
    $text_elements = $phpWord->getSections()[0]->getElements();
    
    foreach ($text_elements as $element) {
        // Count the words in each text element
        $element_text = $element->getText();
        $element_text=str_replace(PHP_EOL, ' ', $element_text);
        $words = str_word_count($element_text);
      
        $word_count += $words;
    }

    return $word_count;
}


function contarPalabrasEnPptx($filePath){
    $zip = new ZipArchive;
    if ($zip->open($filePath) === true) {
        // Crear un directorio temporal para extraer los archivos
        $tempDir = sys_get_temp_dir() . '/pptx_temp/';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        // Extraer el contenido del archivo PPTX al directorio temporal
        if ($zip->extractTo($tempDir)) {
            // Contar las palabras en archivos XML dentro del directorio temporal
            $totalWords = countWordsInXmlFiles($tempDir);

            // Eliminar el directorio temporal y su contenido
            deleteDirectory($tempDir);

            return $totalWords;
        } else {
            // Manejar error de extracción
            echo "Error al extraer el archivo PPTX";
            return false;
        }
    } else {
        // Manejar error de apertura del archivo PPTX
        echo "Error al abrir el archivo PPTX";
        return false;
    }
}

function countWordsInXmlFiles($directory) {
    $totalWords = 0;

    // Obtener la lista de archivos XML en el directorio
    $xmlFiles = glob($directory . 'ppt/slides/slide*.xml'); // Ajusta el número del slide según tus necesidades

    // Recorrer cada archivo XML
    foreach ($xmlFiles as $xmlFile) {
        // Leer el contenido del archivo XML
        $xmlContent = file_get_contents($xmlFile);

        if ($xmlContent === false) {
            echo "Error al leer el contenido del archivo XML: $xmlFile";
            continue;
        }

        // Utilizar SimpleXML para analizar la estructura XML y contar las palabras
        $xml = simplexml_load_string($xmlContent);

        // Contar las palabras en el contenido del archivo XML
        $totalWords += countWordsInXml($xml);
    }

    return $totalWords;
}

function countWordsInXml($xml) {
    $totalWords = 0;

    // Recorrer cada elemento de texto en el XML
    foreach ($xml->xpath('//a:t') as $textElement) {
        // Contar las palabras en cada elemento de texto
        $totalWords += str_word_count((string)$textElement);
    }

    return $totalWords;
}
function deleteDirectory($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir . "/" . $object)) {
                    deleteDirectory($dir . "/" . $object);
                } else {
                    unlink($dir . "/" . $object);
                }
            }
        }
        rmdir($dir);
    }
}



function contarPalabrasEnPpt($filePath){
    $totalWords = 0;

    // Cargar el archivo PPTX
    $pptx = powerpoint::load($filePath);

    // Recorrer cada slide
    foreach ($pptx->getAllSlides() as $slide) {
        // Recorrer cada forma en el slide
        foreach ($slide->getShapeCollection() as $shape) {
            // Obtener el texto de la forma y contar las palabras
            $text = strip_tags($shape->getText());
            $totalWords += str_word_count($text);
        }
    }

    return $totalWords;
}
    


function contarPalabrasEnDocx($docxFilePath) {
    $wordCount = 0;

    if (is_file($docxFilePath)) {
        $contentXml = file_get_contents("zip://$docxFilePath#word/document.xml");

        if ($contentXml !== false) {
            $xml = simplexml_load_string($contentXml);

            if ($xml !== false) {
                $text = strip_tags($xml->asXML());
                $text = preg_replace('/<[^>]*>/', '', $text);
                $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

                $wordCount = count($words);
            }
        }
    }

    return $wordCount;
}






function contarPalabrasEnPdf($pdfFilePath){
    
// Create a new instance of the PDF parser
    $parser = new \Smalot\PdfParser\Parser();
    // Parse the PDF file and get the text content
    $pdf = $parser->parseFile($pdfFilePath);
    $text = $pdf->getText();

    // Remove special characters and images from the text
    $text = preg_replace('/[^a-zA-Z0-9\s]/', '', $text);

    // Count the words in the text
    $wordCount = str_word_count($text);

    return $wordCount;

}


function contarPalabrasEnOdt($odtFilePath) {
       $zip = new ZipArchive();
    if ($zip->open($odtFilePath) === true) {
        $contentXml = $zip->getFromName('content.xml');
        $zip->close();

        // Elimina etiquetas XML y caracteres especiales
        $text = strip_tags($contentXml);
        $text = preg_replace('/\s+/', ' ', $text); // Reemplaza múltiples espacios en blanco con uno solo

        // Cuenta las palabras
        $wordCount = str_word_count($text);

        return $wordCount;
    } else {
        return 0; // No se pudo abrir el archivo ODT
    }
}



function contarPalabrasEnArchivoExcel2( $nombreArchivo ) {
   // Crea un lector de Excel


       $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($nombreArchivo);
        $contadorPalabras = 0;

        // Itera a través de las hojas del archivo Excel
       $worksheet = $spreadsheet->getActiveSheet();

    foreach ($worksheet->getRowIterator() as $row) {

        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(FALSE);
        foreach ($cellIterator as $cell) {
           
                 $contenido=$cell->getValue();
                 $contenido=utf8_encode($contenido);
                 $contenidoLimpio = preg_replace('/\s+/', ' ', $contenido);
                $palabras = str_word_count($contenidoLimpio);

                $contadorPalabras += $palabras;
        }
       
    }
        return $contadorPalabras;
}

function contarPalabrasEnArchivoExcel( $nombreArchivo ) {
   // Crea un lector de Excel
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($nombreArchivo);

  

    $worksheet = $spreadsheet->getActiveSheet();
    $cellIterator = $worksheet->getRowIterator();

    $totalPalabras = 0;

    foreach ($cellIterator as $row) {
        $cellIterator = $row->getCellIterator();

        foreach ($cellIterator as $cell) {
            $cellValue = $cell->getValue();
            // Elimina caracteres especiales y de codificación especial y divide el texto en palabras
            $palabras = preg_split('/\s+/', preg_replace('/[^\p{L}\p{N}\s]/u', '', $cellValue));

            foreach ($palabras as $palabra) {
                // Si la palabra no está vacía, aumenta el contador
                if (!empty($palabra)) {
                    $totalPalabras++;
                }
            }
        }
    }

    return $totalPalabras;

}


function contarPalabrasEnDocumento($archivo) {

    $extension = pathinfo($archivo, PATHINFO_EXTENSION);
    
    var_dump($extension);

    switch ($extension) {
        case 'txt':
            return contarPalabrasEnTxt($archivo);
           case 'doc':
        
        return contarPalabrasEnDoc($archivo);
            
        case 'docx':
            return contarPalabrasEnDocx($archivo);
        case 'pdf':
            return contarPalabrasEnPdf($archivo);
        case 'odt':
        return contarPalabrasEnOdt($archivo);
        case 'xls':
        case 'xlsx':
        return contarPalabrasEnArchivoExcel($archivo);
        case 'pptx':
        return contarPalabrasEnPptx($archivo);
        case 'ppt':
        case 'pps':
        return contarPalabrasEnPpt($archivo);
      
        default:
            return "Formato de archivo no compatible";
    }
} 

// Función para obtener la tarifa de traducción desde la base de datos
/*function obtener_tarifa_traduccion($idioma_origen, $idioma_destino) {
    // Obtener las tarifas de traducción almacenadas en la base de datos
    $tarifas_guardadas = get_option('traduccion_calculadora_tarifas', array());

    // Buscar la tarifa correspondiente en la lista de tarifas
    foreach ($tarifas_guardadas as $tarifa) {
        if ($tarifa['origen'] === $idioma_origen && $tarifa['destino'] === $idioma_destino) {
            return $tarifa['coste'];
        }
    }

    return false; // Tarifa no encontrada
} */

function obtener_tarifa_traduccion($idioma_origen, $idioma_destino, $tipo) {
    // Obtener las tarifas de traducción almacenadas en la base de datos
    $tarifas_guardadas = get_option('traduccion_calculadora_tarifas', array());

    // Buscar la tarifa correspondiente en la lista de tarifas
    foreach ($tarifas_guardadas as $tarifa) {
        if ($tarifa['origen'] === $idioma_origen && $tarifa['destino'] === $idioma_destino && $tarifa['tipo']== $tipo ) {
            return $tarifa['coste'];
        }
    }

    return false; // Tarifa no encontrada
}



// Función para generar una lista de idiomas desde la base de datos
function generar_lista_idiomas() {
    // Obtener los idiomas almacenados en la base de datos
    $idiomas_guardados = get_option('traduccion_calculadora_idiomas', array());

    // Generar las opciones HTML para cada idioma
    $opciones_html = '';
    foreach ($idiomas_guardados as $idioma) {
        $opciones_html .= '<option value="' . esc_attr($idioma) . '">' . esc_html($idioma) . '</option>';
    }

    return $opciones_html;
}


function generar_lista_idiomas_org() {
    // Obtener los idiomas almacenados en la base de datos
    $tarifas_guardadas = get_option('traduccion_calculadora_tarifas', array());
    // Generar las opciones HTML para cada idioma
    $idiomas_org=array();
    $opciones_html = '<option value="0">Selecciona Idioma...</option>';
    foreach ($tarifas_guardadas as $tarifa) {

        if(!in_array($tarifa['origen'], $idiomas_org)){
            $opciones_html .= '<option value="' . $tarifa['origen'] . '">' . $tarifa['origen'] . '</option>';

            array_push($idiomas_org, $tarifa['origen']);
        }
             
    }
   return $opciones_html;
}


function generar_lista_idiomas_des() {
    // Obtener los idiomas almacenados en la base de datos
    $idiomas_guardados = get_option('traduccion_calculadora_idiomas', array());

    // Generar las opciones HTML para cada idioma
    $opciones_html = '<option value="0">Selecciona Idioma...</option>';
    foreach ($idiomas_guardados as $idioma) {
        $opciones_html .= '<option value="' . esc_attr($idioma) . '">' . esc_html($idioma) . '</option>';
    }

    return $opciones_html;
}


// Función para obtener la moneda utilizada para el coste de traducción.
function obtener_moneda() {
    // Devuelve la moneda utilizada
    $moneda = '€';
    return $moneda;
}


// Acciones de WordPress para mostrar el formulario y procesar el envío
add_shortcode('traduccion_calculadora', 'traduccion_calculadora_form');


// Acción para agregar una página de configuración en el menú de administración
function agregar_pagina_configuracion() {
    add_menu_page(
        'Configuración de Tarifas e Idiomas de Traducción',
        'Configuración de Traducción',
        'manage_options',
        'configuracion-traduccion',
        'mostrar_pagina_configuracion'
    );
}
add_action('admin_menu', 'agregar_pagina_configuracion');


// Función para mostrar la página de configuración
function mostrar_pagina_configuracion() {
    // Verificar si el usuario actual tiene permisos para administrar opciones
    if (!current_user_can('manage_options')) {
        wp_die('Acceso denegado.');
    }

    // Procesar la lista de idiomas si se envió el formulario de agregar idioma
    if (isset($_POST['agregar_idioma'])) {
        $nuevo_idioma = sanitize_text_field($_POST['nuevo_idioma']);
        if (!empty($nuevo_idioma)) {
            $idiomas_guardados = get_option('traduccion_calculadora_idiomas', array());
            $idiomas_guardados[] = $nuevo_idioma;
            update_option('traduccion_calculadora_idiomas', $idiomas_guardados);
        }
    }

    // Procesar el formulario para eliminar idioma
    if (isset($_POST['eliminar_idioma'])) {
        $idioma_a_eliminar = sanitize_text_field($_POST['eliminar_idioma']);
        if (!empty($idioma_a_eliminar)) {
            $idiomas_guardados = get_option('traduccion_calculadora_idiomas', array());
            if (($key = array_search($idioma_a_eliminar, $idiomas_guardados)) !== false) {
                unset($idiomas_guardados[$key]);
                update_option('traduccion_calculadora_idiomas', $idiomas_guardados);
            }
        }
    }
   

    if (isset($_POST['agregar_tarifa'])) {

        $tarifa = array(
            'origen' => sanitize_text_field($_POST['origen']),
            'destino' => sanitize_text_field($_POST['destino']),
            'tipo' => sanitize_text_field($_POST['tipo']),
            'coste' => floatval($_POST['coste']),
        );

        if (!empty($tarifa['origen']) && !empty($tarifa['destino']) && $tarifa['coste'] > 0) {
            $tarifas_guardadas = get_option('traduccion_calculadora_tarifas', array());

            $existe_key = null;

            // Buscar la tarifa existente
            foreach ($tarifas_guardadas as $key => $tarifa_existente) {
                if ($tarifa_existente['origen'] === $tarifa['origen'] && $tarifa_existente['destino'] === $tarifa['destino'] && $tarifa_existente['tipo'] === $tarifa['tipo']) {
                    $existe_key = $key;
                    break;
                }
            }

            if ($existe_key !== null) {
                // Si la tarifa existe, actualizar los datos
                $tarifas_guardadas[$existe_key] = $tarifa;
            } else {
                // Si la tarifa no existe, agregarla
                $tarifas_guardadas[] = $tarifa;
            }

            // Actualizar la opción en la base de datos
            update_option('traduccion_calculadora_tarifas', $tarifas_guardadas);
        }
    }

    // Procesar el formulario para eliminar tarifa de traducción
    if (isset($_POST['eliminar_tarifa'])) {
        $tarifa_a_eliminar = intval($_POST['eliminar_tarifa']);
        if ($tarifa_a_eliminar >= 0) {
            $tarifas_guardadas = get_option('traduccion_calculadora_tarifas', array());
            if (isset($tarifas_guardadas[$tarifa_a_eliminar])) {
                unset($tarifas_guardadas[$tarifa_a_eliminar]);
                update_option('traduccion_calculadora_tarifas', array_values($tarifas_guardadas));
            }
        }
    }

    // Obtener la lista de idiomas almacenados en la base de datos
    $idiomas_guardados = get_option('traduccion_calculadora_idiomas', array());

    // Obtener las tarifas almacenadas en la base de datos
    $tarifas_guardadas = get_option('traduccion_calculadora_tarifas', array());

    // Mostrar el formulario de configuración de idiomas y tarifas
    ?>
    <div class="wrap">
        <h2>Configuración de Tarifas e Idiomas de Traducción</h2>

        <!-- Formulario para agregar/editar idiomas -->
        <h3>Idiomas</h3>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th scope="row">Nuevo Idioma:</th>
                    <td>
                        <input type="text" name="nuevo_idioma" required>
                        <input type="submit" name="agregar_idioma" value="Agregar">
                    </td>
                </tr>
            </table>
        </form>

        <table class="form-table" id="tabla-idiomas">
            <tr>
                <th scope="row">Idioma</th>
                <th scope="row">Acciones</th>
            </tr>
            <?php
            foreach ($idiomas_guardados as $idioma) {
                echo '<tr>';
                echo '<td>' . esc_html($idioma) . '</td>';
                echo '<td>';
                echo '<form method="post">';
                echo '<input type="hidden" name="eliminar_idioma" value="' . esc_attr($idioma) . '">';
                echo '<input type="submit" value="Eliminar" onclick="return confirmarEliminarIdioma()">';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
            ?>
        </table>

        <!-- Formulario para agregar/editar tarifas -->
        <h3>Tarifas de Traducción</h3>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th scope="row">Idioma de origen:</th>
                    <td>
                        <select name="origen">
                           <option value="0">Selecciona Idioma...</option>
                            <?php
                            foreach ($idiomas_guardados as $idioma) {
                                echo '<option value="' . esc_attr($idioma) . '">' . esc_html($idioma) . '</option>';
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Idioma de destino:</th>
                    <td>
                        <select name="destino">
                            <option value="0">Selecciona Idioma...</option>
                            <?php
                            foreach ($idiomas_guardados as $idioma) {
                                echo '<option value="' . esc_attr($idioma) . '">' . esc_html($idioma) . '</option>';
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Tipo de traducción:</th>
                    <td>
                        <select name="tipo">
                            <?php
                            $tipo_de_traduccion=array('Normal','Urgente');
                            foreach ($tipo_de_traduccion as $tipo) {
                                echo '<option value="' . esc_attr($tipo) . '">' . esc_html($tipo) . '</option>';
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Coste por palabra:</th>
                    <td>
                        <input type="number" step="0.01" name="coste" required>
                        <input type="submit" name="agregar_tarifa" value="Agregar">
                    </td>
                </tr>
            </table>
        </form>
        <hr>
        <center><h3>TABLA DE PRECIOS POR TRADUCCIÓN</h3></center>

        <table class="form-table" id="tabla-tarifas">
            <tr>
                <th scope="row">Idioma de origen</th>
                <th scope="row">Idioma de destino</th>
                <th scope="row">Tipo traducción</th>
                <th scope="row">Coste por palabra</th>
                <th scope="row">Acciones</th>
            </tr>
            <?php
            foreach ($tarifas_guardadas as $index => $tarifa) {
                echo '<tr>';
                echo '<td>' . esc_html($tarifa['origen'])  . '</td>';
                echo '<td>' . esc_html($tarifa['destino']) . '</td>';
                echo '<td>'. esc_html($tarifa['tipo']) . '</td>';
                echo '<td>' . esc_html($tarifa['coste']) . '</td>';
                echo '<td>';
                echo '<form method="post">';
                echo '<input type="hidden" name="eliminar_tarifa" value="' . $index . '">';
                echo '<input type="submit" value="Eliminar" onclick="return confirmarEliminarTarifa()">';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
            ?>
        </table>
    </div>
    <?php

}

