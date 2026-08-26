#!/bin/sh
set -eu

mkdir -p /etc/asterisk/pjsip.d
umask 077
rm -f /etc/asterisk/manager.conf
envsubst < /opt/thconect-asterisk/manager.conf.template > /etc/asterisk/manager.conf
envsubst < /opt/thconect-asterisk/rtp.conf.template > /etc/asterisk/rtp.conf
cp /opt/thconect-asterisk/asterisk.conf /etc/asterisk/asterisk.conf
cp /opt/thconect-asterisk/extensions.conf /etc/asterisk/extensions.conf
cp /opt/thconect-asterisk/modules.conf /etc/asterisk/modules.conf
cp /opt/thconect-asterisk/http.conf /etc/asterisk/http.conf

printf '[general]\n' > /etc/asterisk/ice.conf
if [ -n "${ASTERISK_STUN_ADDRESS:-}" ]; then
    printf 'stunaddr=%s\n' "$ASTERISK_STUN_ADDRESS" >> /etc/asterisk/ice.conf
fi

touch /etc/asterisk/pjsip.d/thconect-routes.conf
touch /etc/asterisk/pjsip.d/thconect-agents.conf
chmod 600 /etc/asterisk/manager.conf /etc/asterisk/pjsip.d/thconect-routes.conf /etc/asterisk/pjsip.d/thconect-agents.conf

write_transport() {
    name="$1"
    protocol="$2"
    bind="$3"

    {
        printf '\n[%s]\n' "$name"
        printf 'type=transport\nprotocol=%s\nbind=%s\nallow_reload=yes\n' "$protocol" "$bind"
        printf 'local_net=%s\n' "${ASTERISK_LOCAL_NET_1:-172.16.0.0/12}"
        printf 'local_net=%s\n' "${ASTERISK_LOCAL_NET_2:-192.168.0.0/16}"
        printf 'local_net=%s\n' "${ASTERISK_LOCAL_NET_3:-10.0.0.0/8}"
        if [ -n "${ASTERISK_EXTERNAL_MEDIA_ADDRESS:-}" ]; then
            printf 'external_media_address=%s\n' "$ASTERISK_EXTERNAL_MEDIA_ADDRESS"
        fi
        if [ -n "${ASTERISK_EXTERNAL_SIGNALING_ADDRESS:-}" ]; then
            printf 'external_signaling_address=%s\n' "$ASTERISK_EXTERNAL_SIGNALING_ADDRESS"
            printf 'external_signaling_port=%s\n' "${ASTERISK_EXTERNAL_SIGNALING_PORT:-5060}"
        fi
    } >> /etc/asterisk/pjsip.conf
}

cat > /etc/asterisk/pjsip.conf <<'EOF'
[global]
type=global
user_agent=THCONECT Asterisk
max_initial_qualify_time=4
EOF

write_transport transport-udp udp 0.0.0.0:5060
write_transport transport-tcp tcp 0.0.0.0:5060

cat >> /etc/asterisk/pjsip.conf <<'EOF'

[transport-ws]
type=transport
protocol=ws
bind=0.0.0.0
allow_reload=yes
EOF

if [ "${ASTERISK_ENABLE_TLS:-false}" = "true" ]; then
    write_transport transport-tls tls 0.0.0.0:5061
    {
        printf 'method=tlsv1_2\nverify_client=no\nverify_server=yes\n'
    } >> /etc/asterisk/pjsip.conf
fi

cat >> /etc/asterisk/pjsip.conf <<'EOF'

; Managed by the internal bridge. Do not edit this include manually.
#include pjsip.d/thconect-routes.conf
#include pjsip.d/thconect-agents.conf
EOF

# Recordings must remain readable by the application in the shared volume.
umask 022

exec "$@"
