#!/bin/sh
set -eu

mkdir -p /etc/asterisk/pjsip.d
envsubst < /opt/thconect-asterisk/manager.conf.template > /etc/asterisk/manager.conf
envsubst < /opt/thconect-asterisk/rtp.conf.template > /etc/asterisk/rtp.conf
cp /opt/thconect-asterisk/asterisk.conf /etc/asterisk/asterisk.conf
cp /opt/thconect-asterisk/pjsip.conf /etc/asterisk/pjsip.conf
cp /opt/thconect-asterisk/extensions.conf /etc/asterisk/extensions.conf

touch /etc/asterisk/pjsip.d/thconect-routes.conf

exec "$@"
