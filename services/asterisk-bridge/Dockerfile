FROM golang:1.22-alpine AS build
WORKDIR /src
COPY go.mod ./
COPY cmd ./cmd
RUN CGO_ENABLED=0 GOOS=linux go build -trimpath -ldflags="-s -w" -o /asterisk-bridge ./cmd/bridge

FROM alpine:3.20
RUN addgroup -S bridge && adduser -S bridge -G bridge
COPY --from=build /asterisk-bridge /usr/local/bin/asterisk-bridge
USER bridge
EXPOSE 8090
ENTRYPOINT ["/usr/local/bin/asterisk-bridge"]
