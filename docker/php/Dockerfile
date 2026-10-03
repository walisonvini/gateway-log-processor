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

# Copiar aplicação
COPY . /var/www

# Expor a porta do servidor do Laravel
EXPOSE 8000

# Executar o servidor embutido do Laravel bindando em todas as interfaces de rede
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]