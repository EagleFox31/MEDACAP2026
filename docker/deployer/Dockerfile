# Outils de deploy.sh pour un serveur Windows sans distribution Linux (deploy-server.ps1) :
# bash, git, ssh, flock et client Docker + compose, qui pilote le Docker de la machine par son socket.
FROM docker:29-cli
RUN apk add --no-cache bash coreutils flock git openssh-client \
 && git config --system --add safe.directory '*' \
 && mkdir -p /root/.ssh
COPY run.sh /usr/local/bin/medacap-deploy
ENTRYPOINT ["bash", "/usr/local/bin/medacap-deploy"]
