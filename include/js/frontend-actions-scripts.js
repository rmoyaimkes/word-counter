function confirmarEliminarTarifa() {
    // Mostrar un cuadro de confirmación
    var confirmacion = confirm("¿Estás seguro eliminar la tarifa?");
        
    // Si el usuario hace clic en "Aceptar", permitir el envío del formulario
    return confirmacion;
}


function confirmarEliminarIdioma(){
     // Mostrar un cuadro de confirmación
    var confirmacion = confirm("¿Estás seguro eliminar el idioma?");
    
    // Si el usuario hace clic en "Aceptar", permitir el envío del formulario
    return confirmacion;
}



  document.addEventListener('DOMContentLoaded', function () {
    // Obtener los elementos del formulario
    var idiomaOrigenSelect = document.getElementById('idioma_origen');
    var idiomaDestinoSelect = document.getElementById('idioma_destino');

     idiomaDestinoSelect.addEventListener('click', function () {

      if(idiomaDestinoSelect.length==0){
        alert("Selecciona primero idioma de origen");
      } 

     });


    // Agregar un evento al cambio de idioma origen
    idiomaOrigenSelect.addEventListener('change', function () {
        // Obtener el valor del idioma de origen seleccionado
        var idiomaSeleccionado = idiomaOrigenSelect.value;


        // Realizar la llamada AJAX
        jQuery.post({
            url: "/wp-admin/admin-ajax.php",
            data: {
                action: 'limpiar_idiomas',
                idioma_origen: idiomaSeleccionado
            },
           success: function (response) {
                // Limpiar el select de idiomas destino
                idiomaDestinoSelect.innerHTML = '';

                // Parsear la respuesta JSON
                var idiomasDisponibles = JSON.parse(response);

                // Agregar las opciones al select de idiomas destino
                idiomasDisponibles.forEach(function (idioma) {
                    var option = document.createElement('option');
                    option.value = idioma;
                    option.text = idioma;
                    idiomaDestinoSelect.add(option);
                });
            },
            error: function (error) {
                console.error('Error en la llamada AJAX:', error);
            }
        });
    });
});



