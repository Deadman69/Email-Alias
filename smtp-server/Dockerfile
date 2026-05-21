FROM node:22-alpine

WORKDIR /app

# Copy lock file first for layer caching
COPY --chown=node:node package.json package-lock.json ./
RUN npm ci --omit=dev

COPY --chown=node:node src/ ./src/

USER node

# SMTP receiver (port 25 requires CAP_NET_BIND_SERVICE or rootful container in Podman)
EXPOSE 25
# Health-check + metrics HTTP endpoint
EXPOSE 8025

CMD ["node", "src/index.js"]
