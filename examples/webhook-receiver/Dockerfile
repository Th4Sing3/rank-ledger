FROM node:22-alpine

WORKDIR /app

COPY package.json ./
COPY lib ./lib
COPY test ./test
COPY server.mjs ./

USER node

EXPOSE 8081

CMD ["node", "server.mjs"]
