FROM debian:bookworm-slim AS build

ARG ASTERISK_VERSION=22.11.0
ARG ASTERISK_SHA256=3bd5ee040509a3d3cd9b1ba9520c18e6ec0a7e7981ca68c457dcd36ba3c54d94

RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        build-essential ca-certificates curl pkg-config bison flex file \
        libedit-dev libjansson-dev libsqlite3-dev uuid-dev libxml2-dev libssl-dev \
        libncurses-dev libcurl4-openssl-dev libnewt-dev libspeex-dev libspeexdsp-dev \
        libogg-dev libvorbis-dev libasound2-dev libsrtp2-dev \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /usr/src

RUN curl --fail --location --show-error --silent \
        "https://downloads.asterisk.org/pub/telephony/asterisk/asterisk-${ASTERISK_VERSION}.tar.gz" \
        --output asterisk.tar.gz \
    && echo "${ASTERISK_SHA256}  asterisk.tar.gz" | sha256sum --check --strict \
    && mkdir asterisk \
    && tar --extract --gzip --file asterisk.tar.gz --directory asterisk --strip-components=1

WORKDIR /usr/src/asterisk

RUN ./configure \
        --with-pjproject-bundled \
        --with-jansson-bundled \
        --without-dahdi \
        --without-pri \
        --without-bluetooth \
    && make menuselect.makeopts \
    && menuselect/menuselect --disable BUILD_NATIVE \
        --enable res_http_websocket \
        --enable res_pjsip_transport_websocket \
        menuselect.makeopts \
    && make -j"$(nproc)" \
    && make DESTDIR=/opt/asterisk install \
    && make DESTDIR=/opt/asterisk samples

FROM debian:bookworm-slim

RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        ca-certificates gettext-base libasound2 libcurl4 libedit2 libjansson4 \
        libncurses6 libnewt0.52 libogg0 libsodium23 libspeex1 libspeexdsp1 \
        libsqlite3-0 libsrtp2-1 libssl3 liburiparser1 libuuid1 libvorbis0a libxml2 \
        libvorbisenc2 libvorbisfile3 \
    && rm -rf /var/lib/apt/lists/*

COPY --from=build /opt/asterisk/usr/ /usr/
COPY --from=build /opt/asterisk/etc/ /etc/
COPY --from=build /opt/asterisk/var/lib/asterisk/ /var/lib/asterisk/
COPY --from=build /opt/asterisk/var/spool/asterisk/ /var/spool/asterisk/
COPY --from=build /opt/asterisk/var/log/asterisk/ /var/log/asterisk/
COPY config/ /opt/thconect-asterisk/
COPY entrypoint.sh /usr/local/bin/thconect-asterisk-entrypoint

RUN chmod +x /usr/local/bin/thconect-asterisk-entrypoint \
    && mkdir -p /etc/asterisk /var/lib/asterisk/sounds /var/spool/asterisk/monitor/thconect /var/log/asterisk

EXPOSE 5038 5060/tcp 5060/udp 5061/tcp 8088/tcp 10000-10199/udp

ENTRYPOINT ["thconect-asterisk-entrypoint"]
CMD ["asterisk", "-f", "-vvv"]
