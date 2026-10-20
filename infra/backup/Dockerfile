FROM postgres:17-alpine

RUN apk add --no-cache gnupg coreutils
COPY backup.sh /usr/local/bin/ministry-backup
RUN chmod 0755 /usr/local/bin/ministry-backup

ENTRYPOINT ["/usr/local/bin/ministry-backup"]
