<?php
$icon_src = \Akamai\WordPress\Admin\Admin::get_icon();
?>
<div
    id="<?= esc_attr( $this->id_attr() ) ?>"
    class="<?= esc_attr( $this->classname() ) ?>"
    data-nonce="<?= esc_attr( $this->nonce() ) ?>">
    <p><img src="<?= $icon_src ?>" style="height: 1em; position: relative; top: 1px;" alt="Akamai for WordPress">&nbsp;
    Akamai: <?= wp_kses_post( __( $this->message, \Akamai\WordPress\Plugin::$identifier ) ) ?></p>
    <?php if ( $this->dismissible() ): ?>
    <button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
    <?php endif; ?>
</div>
