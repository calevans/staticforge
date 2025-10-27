#! /bin/bash
touch ~/.bash_profile && (cd /tmp && ([[ -d sexy-bash-prompt ]] || git clone --depth 1 --config core.autocrlf=false https://github.com/twolfson/sexy-bash-prompt) && cd sexy-bash-prompt && make install) && source ~/.bashrc && rm -rf /tmp/sexy-bash-prompt

if [ -f /app/composer.json ]
then
  noop
  composer require --dev squizlabs/php_codesniffer
  composer install
  $COMPOSER_VENDOR_DIR/bin/phpcs --config-set default_standard PSR12 \
  $COMPOSER_VENDOR_DIR/bin/phpcs --config-set tab_width 2 \
  $COMPOSER_VENDOR_DIR/bin/phpcs --config-set colors 1 \
  $COMPOSER_VENDOR_DIR/bin/phpcs --config-set show_progress 1
fi
