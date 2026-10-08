<?php
// Reutilizado desde crear y editar video; $generosDisponibles / $generosSeleccionados.
$generosMarcados = array_map('intval', $generosSeleccionados ?? []);
?>
<fieldset class="genre-video-fieldset">
    <legend>Géneros del video</legend>
    <p>Puedes seleccionar uno o varios géneros. La categoría define el tipo de contenido; los géneros describen su temática.</p>
    <div class="genre-video-grid">
        <?php foreach(($generosDisponibles ?? []) as $generoOpcion): ?>
        <label class="genre-video-option">
            <input type="checkbox" name="generos[]" value="<?php echo (int)$generoOpcion['id_genero']; ?>" <?php echo in_array((int)$generoOpcion['id_genero'], $generosMarcados, true) ? 'checked' : ''; ?>>
            <span><?php echo htmlspecialchars($generoOpcion['nombre'], ENT_QUOTES, 'UTF-8'); ?></span>
        </label>
        <?php endforeach; ?>
    </div>
    <?php if (empty($generosDisponibles)): ?><p>Primero activa los géneros en administración.</p><?php endif; ?>
    <p><a href="../generos/listar.php">Administrar géneros →</a></p>
</fieldset>
