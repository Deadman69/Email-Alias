FROM node:22-alpine

WORKDIR /app

COPY package.json .
RUN npm install --production

COPY src/ ./src/

# Port 25 nécessite CAP_NET_BIND_SERVICE ou root dans Podman
# En prod, utiliser un port forwarding ou rootful container
EXPOSE 25

CMD ["node", "src/index.js"]
