FROM debian:bookworm-slim

RUN apt-get update && apt-get install -y --no-install-recommends tor \
    && rm -rf /var/lib/apt/lists/*

COPY docker/tor/torrc /etc/tor/torrc

EXPOSE 9050 9051

# The Debian tor package pre-creates /var/lib/tor owned by debian-tor and
# Tor refuses to run as a different user against it — run as that user
# rather than fighting the package's own permissions.
USER debian-tor

CMD ["tor", "-f", "/etc/tor/torrc"]
