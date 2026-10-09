<?php
/**
 * DEVIOZ VIDEOS V4.5.5.1: modal de compartir despues de una evaluacion.
 * La plataforma nunca envia mensajes: abre el compositor oficial de WhatsApp.
 * No se guarda el numero del destinatario ni el comentario en la BD o la sesion.
 */
if (empty($intento) || empty($video['titulo'])) {
    return;
}
require_once __DIR__ . '/whatsapp_url_publica.php';
$enlacePublicoDelVideo = null;
try {
    require_once __DIR__ . '/../models/Configuracion.php';
    $configuracionCompartir = new Configuracion();
    $enlacePublicoDelVideo = deviozUrlPublicaVideo(
        (int)$idVideo,
        $configuracionCompartir->obtener('url_publica_sitio')
    );
} catch (Throwable $e) {
    error_log('DEVIOZ V4.5.5.1 - No se pudo consultar la URL publica: ' . $e->getMessage());
}

$paisesWhatsapp = [
    'América Latina y el Caribe' => [
        ['Argentina', '54'], ['Bolivia', '591'], ['Brasil', '55'],
        ['Chile', '56'], ['Colombia', '57'], ['Costa Rica', '506'],
        ['Cuba', '53'], ['Ecuador', '593'], ['El Salvador', '503'],
        ['Guatemala', '502'], ['Haití', '509'], ['Honduras', '504'],
        ['México', '52'], ['Nicaragua', '505'], ['Panamá', '507'],
        ['Paraguay', '595'], ['Perú', '51'], ['República Dominicana', '1'],
        ['Uruguay', '598'], ['Venezuela', '58'],
    ],
    'América del Norte' => [
        ['Canadá', '1'], ['Estados Unidos', '1'],
    ],
    'Europa' => [
        ['Alemania', '49'], ['Bélgica', '32'], ['Dinamarca', '45'],
        ['España', '34'], ['Finlandia', '358'], ['Francia', '33'],
        ['Grecia', '30'], ['Irlanda', '353'], ['Italia', '39'],
        ['Noruega', '47'], ['Países Bajos', '31'], ['Polonia', '48'],
        ['Portugal', '351'], ['Reino Unido', '44'], ['Rumanía', '40'],
        ['Suecia', '46'], ['Suiza', '41'], ['Ucrania', '380'],
    ],
    'Asia y Oriente Medio' => [
        ['Arabia Saudita', '966'], ['China', '86'], ['Corea del Sur', '82'],
        ['Emiratos Árabes Unidos', '971'], ['Filipinas', '63'],
        ['India', '91'], ['Indonesia', '62'], ['Israel', '972'],
        ['Japón', '81'], ['Pakistan', '92'], ['Tailandia', '66'],
        ['Turquía', '90'],
    ],
    'Oceania y Africa' => [
        ['Australia', '61'], ['Egipto', '20'], ['Marruecos', '212'],
        ['Nueva Zelanda', '64'], ['Nigeria', '234'], ['Sudáfrica', '27'],
    ],
];
?>
<dialog
    class="vq-wa-dialog"
    id="vqWhatsappDialog"
    aria-labelledby="vqWhatsappTitulo"
    aria-describedby="vqWhatsappDescripcion"
    data-abrir-auto="<?php echo !empty($abrirCompartirWhatsApp) ? '1' : '0'; ?>"
    data-video-id="<?php echo (int)$idVideo; ?>"
    data-video-titulo="<?php echo $h($video['titulo']); ?>"
    data-video-enlace-publico="<?php echo $h($enlacePublicoDelVideo ?? ''); ?>">
    <div class="vq-wa-interior">
        <div class="vq-wa-cabecera">
            <span class="vq-wa-icono" aria-hidden="true">✉</span>
            <div>
                <span class="vq-eyebrow">DEVIOZ VIDEOS · V4.5.5.1</span>
                <h2 id="vqWhatsappTitulo">¡Terminaste tu evaluación!</h2>
            </div>
        </div>
        <p id="vqWhatsappDescripcion">Obtuviste <strong><?php echo (int)$intento['puntaje']; ?>/100</strong>. ¿Quieres compartir con alguien algo interesante que aprendiste sobre <strong><?php echo $h($video['titulo']); ?></strong>?</p>
        <form id="vqWhatsappForm" novalidate>
            <label for="vqWhatsappComentario">¿Qué te pareció interesante del video? <span aria-hidden="true">*</span></label>
            <textarea id="vqWhatsappComentario" rows="3" maxlength="600" required placeholder="Ej.: Me pareció muy útil cómo explican las imágenes y contenedores Docker."></textarea>
            <span class="vq-wa-ayuda">Este comentario aparecerá en el mensaje que enviarás desde WhatsApp.</span>
            <div class="vq-wa-telefono">
                <div>
                    <label for="vqWhatsappPais">País y prefijo</label>
                    <select id="vqWhatsappPais" required>
                        <?php foreach ($paisesWhatsapp as $region => $paises): ?>
                        <optgroup label="<?php echo $h($region); ?>">
                            <?php foreach ($paises as [$nombre, $prefijo]): ?>
                            <option value="<?php echo $h($prefijo); ?>" <?php echo $nombre === 'Perú' ? 'selected' : ''; ?>><?php echo $h($nombre); ?> (+<?php echo $h($prefijo); ?>)</option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="vqWhatsappNumero">Número de WhatsApp <span aria-hidden="true">*</span></label>
                    <input id="vqWhatsappNumero" type="tel" inputmode="tel" autocomplete="off" maxlength="22" placeholder="987 654 321" required>
                </div>
            </div>
            <span class="vq-wa-ayuda">Introduce el número nacional sin +51, sin prefijo y sin cero inicial.</span>
            <label for="vqWhatsappEnlace">🔗 Enlace al video para compartir</label>
            <input id="vqWhatsappEnlace" type="url" inputmode="url" maxlength="2000" placeholder="https://tu-dominio.com/DEVIOZ-VIDEOS/public/detalle.php?id=...">
            <span class="vq-wa-ayuda" id="vqWhatsappEnlaceAviso">El enlace se genera con la URL pública del proyecto; puedes ajustarlo antes de compartir.</span>
            <div class="vq-wa-previa">
                <span>💬 Así se verá el mensaje en WhatsApp</span>
                <p id="vqWhatsappVistaPrevia"></p>
            </div>
            <p id="vqWhatsappError" class="vq-wa-error" role="alert" hidden></p>
            <p id="vqWhatsappEstado" class="vq-wa-estado" role="status" hidden></p>
            <div class="vq-wa-acciones">
                <a id="vqWhatsappEnviar" class="vq-primary" href="#" target="_blank" rel="noopener noreferrer">Continuar en WhatsApp ↗</a>
                <button id="vqWhatsappDespues" class="vq-secondary" type="button">Ahora no</button>
            </div>
        </form>
        <small>Se abrirá WhatsApp con tu mensaje listo. <strong>Pulsa Enviar dentro de WhatsApp</strong> para compartirlo. DEVIOZ no puede enviarlo por sí solo ni comprobar su entrega.</small>
    </div>
</dialog>
