
GO

/****** Object:  UserDefinedFunction [dbo].[fn_TraerFundas]    Script Date: 23/08/2024 9:54:53 ******/
DROP FUNCTION [dbo].[fn_TraerFundas]
GO

/****** Object:  UserDefinedFunction [dbo].[fn_TraerFundas]    Script Date: 23/08/2024 9:54:53 ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO

CREATE FUNCTION [dbo].[fn_TraerFundas] (@Codigo varchar(20))
returns TABLE
as
return
(select DISTINCT c.ID 'CODIGO' 
from T_CODIGOS c inner join T_C_TIPOS t on c.ID_C_TIPO = t.ID  
inner join R_MODELO_CODIGO m on RTRIM(c.ID) = RTRIM(m.ID_CODIGO) 
inner join T_MODELOS b on RTRIM(b.ID) = RTRIM(m.ID_MODELO) 
--where RTRIM(m.ID_MODELO) = @Codigo)
where RTRIM(m.ID_MODELO) = @Codigo and (c.ID_C_TIPO<>4 and c.ID_C_TIPO<>3))
GO


