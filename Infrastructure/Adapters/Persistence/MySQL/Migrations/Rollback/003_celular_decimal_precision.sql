-- Check that no stored values exceed the old limits before narrowing these columns.
ALTER TABLE celulares
    MODIFY megapixeles DECIMAL(7,2) NOT NULL,
    MODIFY velocidad_cpu DECIMAL(5,2) NOT NULL;
