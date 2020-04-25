FROM python:3.7.7-alpine
RUN pip install pycparser
RUN apk update
RUN apk add g++
COPY testdata /home/testdata
COPY c-to-json.py /home/c-to-json.py
WORKDIR /home
ENTRYPOINT ["python3"]