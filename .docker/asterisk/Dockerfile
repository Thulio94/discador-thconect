FROM debian:bullseye-slim

RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends asterisk asterisk-doc gettext-base ca-certificates \
    && rm -rf /var/lib/apt/lists/*

COPY config/ /opt/thconect-asterisk/
COPY entrypoint.sh /usr/local/bin/thconect-asterisk-entrypoint

RUN chmod +x /usr/local/bin/thconect-asterisk-entrypoint \
    && mkdir -p /etc/asterisk /var/lib/asterisk/sounds /var/spool/asterisk /var/log/asterisk

EXPOSE 5038 5060/tcp 5060/udp

ENTRYPOINT ["thconect-asterisk-entrypoint"]
CMD ["asterisk", "-f", "-vvv"]
