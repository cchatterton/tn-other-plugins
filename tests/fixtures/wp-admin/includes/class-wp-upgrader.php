<?php
class Automatic_Upgrader_Skin {}
class Plugin_Upgrader {
    public function __construct($skin) {}
    public function bulk_upgrade($files, $args) {
        if (count($files) !== 1 || $args !== ['clear_update_cache' => false]) { throw new RuntimeException('Unexpected update request.'); }
        if ($files[0] === 'first/main.php') { return ['first/main.php' => ['destination' => 'first']]; }
        if ($files[0] === 'second/main.php') { return ['second/main.php' => new WP_Error()]; }
        throw new RuntimeException('Unexpected plugin.');
    }
}
