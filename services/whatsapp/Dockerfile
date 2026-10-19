FROM node:24-bookworm-slim
WORKDIR /app
COPY package*.json ./
RUN npm ci --omit=dev
COPY src ./src
ENV NODE_ENV=production PORT=3000 WHATSAPP_DATA_DIR=/data
RUN mkdir /data && chown node:node /data
USER node
EXPOSE 3000
CMD ["node", "src/index.js"]
