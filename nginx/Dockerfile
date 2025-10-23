# Nginx image with built Vite assets and Laravel public directory

# Stage 1: Build frontend assets
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./vite.config.js
COPY resources ./resources
COPY public ./public
RUN npm run build

# Stage 2: Nginx serving static and proxying to PHP-FPM
FROM nginx:alpine
WORKDIR /var/www/html

# Copy Nginx config
COPY nginx/nginx.conf /etc/nginx/conf.d/default.conf

# Copy public dir and built assets
COPY public ./public
COPY --from=assets /app/public/build ./public/build

EXPOSE 80