-- Match the domain maximums: megapixeles=100000.00 and velocidadCpu=1000.00.
ALTER TABLE celulares
    MODIFY megapixeles DECIMAL(8,2) NOT NULL,
    MODIFY velocidad_cpu DECIMAL(6,2) NOT NULL;
