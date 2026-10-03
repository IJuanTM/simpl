@addon-insert:before('# @addon-placeholder:extensions')
# PDO ships compiled into the base image; its MySQL driver doesn't.
RUN docker-php-ext-install pdo_mysql
@addon-end
