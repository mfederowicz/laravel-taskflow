FROM node:24-alpine

WORKDIR /app

COPY app/frontend/package*.json ./

RUN npm install

EXPOSE 3000

CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
