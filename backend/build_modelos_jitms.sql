SET NOCOUNT ON;

IF OBJECT_ID('dbo.modelos_jitms', 'U') IS NOT NULL
    DROP TABLE dbo.modelos_jitms;

CREATE TABLE dbo.modelos_jitms (
    id                INT IDENTITY(1,1) PRIMARY KEY,
    id_producto       SMALLINT      NULL,
    sd                VARCHAR(20)   NULL,
    sd_id             SMALLINT      NULL,
    codigo            VARCHAR(50)   NULL,
    modelo            VARCHAR(150)  NULL,
    mano              VARCHAR(10)   NULL,
    posicion_mano     VARCHAR(30)   NULL,
    id_familia        TINYINT       NULL,
    familia           VARCHAR(50)   NULL,
    qr                VARCHAR(100)  NULL,
    airbag            INT           NULL,
    posicion          INT           NULL,
    codigo_tasa       VARCHAR(50)   NULL,
    sd_tasa           VARCHAR(50)   NULL,
    descripcion_tasa  VARCHAR(150)  NULL,
    fecha_sync        DATETIME      NOT NULL DEFAULT (GETDATE())
);

INSERT INTO dbo.modelos_jitms
    (id_producto, sd, sd_id, codigo, modelo, mano, posicion_mano,
     id_familia, familia, qr, airbag, posicion, codigo_tasa, sd_tasa, descripcion_tasa)
SELECT
    v.IdProducto,
    NULLIF(RTRIM(LTRIM(v.SD)), ''),
    v.SDID,
    NULLIF(RTRIM(LTRIM(v.Codigo)), ''),
    NULLIF(RTRIM(LTRIM(v.Descripcion)), ''),
    NULLIF(RTRIM(LTRIM(v.Mano)), ''),
    NULLIF(RTRIM(LTRIM(v.PosicionMano)), ''),
    v.IdFamilia,
    NULLIF(RTRIM(LTRIM(f.Descripcion)), ''),
    NULLIF(RTRIM(LTRIM(v.QR)), ''),
    v.Airbag,
    v.Posicion,
    NULLIF(RTRIM(LTRIM(v.CodigoTASA)), ''),
    NULLIF(RTRIM(LTRIM(v.SDTASA)), ''),
    NULLIF(RTRIM(LTRIM(v.DescripcionTASA)), '')
FROM JITMS.CATALOGO.vwProductoTBAR v
LEFT JOIN JITMS.CATALOGO.Familia f ON f.IdFamilia = v.IdFamilia
ORDER BY v.IdProducto, v.SD;

CREATE INDEX IX_modelos_jitms_sd        ON dbo.modelos_jitms (sd);
CREATE INDEX IX_modelos_jitms_idprod    ON dbo.modelos_jitms (id_producto);
CREATE INDEX IX_modelos_jitms_codigo    ON dbo.modelos_jitms (codigo);

SELECT COUNT(*) AS filas_insertadas FROM dbo.modelos_jitms;
