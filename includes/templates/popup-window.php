<div class="dp-wpm-popup">
    <div class="dp-wpm-popup-inner">
        <div class="dp-wpm-hidden-form">
            <div class="dp-wpm-close-button"></div>
            <?php echo (isset($finalPopup) ? $finalPopup : ''); ?>
        </div>
    </div>
</div>

<?php if (isset($_GET['preview'])): ?>
    <p class="buttons" id="dp-wpm-popup-preview">
        <a class="button button-primary" href="#">Показать попап</a>
    </p>
<?php endif; ?>
