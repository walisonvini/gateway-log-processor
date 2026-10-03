FROM php:8.4-cli

# Definir diretório de trabalho
WORKDIR /var/www

# Instalar dependências do sistema
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev

# Limpar o cache do gerenciador de pacotes
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Instalar extensões de PHP necessárias para o Laravel
RUN docker-php-ext-install pdo_mysql mbstring pcntl bcmath zip

# Copiar a versão mais recente do Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Criar usuário com o mesmo UID/GID do host para não gerar arquivos como root no volume
ARG UID=1000
ARG GID=1000
RUN groupadd -g ${GID} app && useradd -u ${UID} -g app -m app

# Copiar aplicação
COPY --chown=app:app . /var/www

# Copiar o entrypoint para fora de /var/www, que é sobrescrito pelo volume
COPY docker/scripts/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Executar o container com o usuário sem privilégios
USER app

# Expor a porta do servidor do Laravel
EXPOSE 8000

# Preparar a aplicação (dependências, APP_KEY e migrations) antes do comando principal
ENTRYPOINT ["entrypoint.sh"]

# Executar o servidor embutido do Laravel bindando em todas as interfaces de rede
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]