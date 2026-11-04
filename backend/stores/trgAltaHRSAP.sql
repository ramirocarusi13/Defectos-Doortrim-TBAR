USE [SAR]
GO
/****** Object:  Trigger [dbo].[trgAltaHRSAP]    Script Date: 23/08/2024 9:49:38 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO

ALTER TRIGGER [dbo].[trgAltaHRSAP] ON [dbo].[T_REGISTROS_COMP]
FOR INSERT
AS 
BEGIN

	SET NOCOUNT ON;	

	DECLARE @codigoHR NVARCHAR(20)
	DECLARE @cantidadHR INT
	DECLARE @fecha datetime
	DECLARE @hora datetime

	SET @fecha = CONVERT(date,GETDATE())
	SET @hora = CONVERT(time,GETDATE())

	SET @cantidadHR  = 0
	SELECT @codigoHR = ID_CODIGO FROM inserted

	SELECT @cantidadHR = ISNULL(T_CANTIDADES.CANTIDAD,0) 
	FROM T_CODIGOS 
	LEFT JOIN T_CANTIDADES
	ON T_CODIGOS.ID_CANTIDAD = T_CANTIDADES.ID
	WHERE T_CODIGOS.ID=@codigoHR AND (T_CODIGOS.ID_C_TIPO=4 or T_CODIGOS.ID_C_TIPO=3)

    IF @cantidadHR > 0
		exec dbo.CargarSAP @codigoHR,@fecha,@hora,@cantidadHR
END
