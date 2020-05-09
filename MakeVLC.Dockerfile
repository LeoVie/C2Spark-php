FROM gcc
RUN apt-get update
RUN mkdir /lua_build
WORKDIR /lua_build
RUN curl -R -O http://www.lua.org/ftp/lua-5.3.4.tar.gz
RUN tar -zxf lua-5.3.4.tar.gz
RUN cd lua-5.3.4 && make linux test && make install
RUN apt-get install -y ffmpeg git build-essential pkg-config libtool automake autopoint gettext
RUN echo "deb-src http://deb.debian.org/debian buster main" | tee -a /etc/apt/sources.list
RUN echo "deb-src http://security.debian.org/debian-security buster/updates main" | tee -a /etc/apt/sources.list
RUN echo "deb-src http://deb.debian.org/debian buster-updates main" | tee -a /etc/apt/sources.list
RUN cat /etc/apt/sources.list
RUN apt-get update
RUN apt-get build-dep -y vlc
WORKDIR /