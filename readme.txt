# Web API Project

## Overview
This API provides web API task execution functionality.

## Required Environment
- PHP >= 7.3 (Do not change version.)
- SQLserver >= 2019
- Apache

## File Structure
```
webAPI/
├── /htdocs
│   └── executeTask.php (Executes web API tasks) https://github.com/ygc-tomas/amr_webapi.git.
└── readme.txt (This instruction manual)
```

## Setup Procedure
1. Download PHP 7.3 and add the necessary PHP configuration file(php.ini) and Microsoft SQL Server Drivers for PHP 
(driver:https://learn.microsoft.com/ja-jp/sql/connect/php/release-notes-php-sql-driver?view=sql-server-ver16).
2. Download Apache2.4 and add the necessary configuration file(httpd.conf).
3. Execute git clone "https://github.com/ygc-tomas/amr_webapi.git" at /htdocs
4. Run the following database creation command in SSMS to create tables (No test data included).
5. Start the server locally on port 8080.

## DB connection Information
ServerName:localhost (or use "localhost¥MSSQLSERVER01")
Authentication:SQL server Authentication
User:test
Password:Koito2025

## Database Creation Command
```sql
USE [amr_task_db]
GO
/****** Object:  User [test]    Script Date: 2025/02/18 10:57:05 ******/
CREATE USER [test] FOR LOGIN [test] WITH DEFAULT_SCHEMA=[dbo]
GO
ALTER ROLE [db_owner] ADD MEMBER [test]
GO
/****** Object:  Table [dbo].[task_list]    Script Date: 2025/02/18 10:57:05 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[task_list](
	[id] [int] IDENTITY(1,1) NOT NULL,
	[mission_id] [varchar](36) NOT NULL,
	[mission_code] [varchar](50) NULL,
	[status] [varchar](20) NOT NULL,
	[sequence] [int] NULL,
	[created_at] [datetime] NULL,
	[updated_at] [datetime] NULL,
	[started_at] [datetime] NULL,
	[end_at] [datetime] NULL,
	[completed_at] [datetime] NULL,
	[error_code] [varchar](50) NULL,
	[error_message] [text] NULL,
	[call_back_url] [nvarchar](50) NULL,
 CONSTRAINT [PK__task_list__0AD2A005] PRIMARY KEY CLUSTERED 
(
	[id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY],
 CONSTRAINT [UQ__task_list__0BC6C43E] UNIQUE NONCLUSTERED 
(
	[mission_id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
/****** Object:  Table [dbo].[task_logs]    Script Date: 2025/02/18 10:57:05 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[task_logs](
	[log_id] [int] IDENTITY(1,1) NOT NULL,
	[mission_id] [nvarchar](50) NOT NULL,
	[mission_code] [nvarchar](50) NULL,
	[runtime_id] [nvarchar](50) NULL,
	[status] [nvarchar](20) NOT NULL,
	[allocation_status] [nvarchar](20) NULL,
	[sequence] [int] NULL,
	[details] [nvarchar](max) NULL,
	[error_code] [int] NULL,
	[message] [nvarchar](max) NULL,
	[start_time] [datetime] NULL,
	[end_time] [datetime] NULL,
	[call_back_url] [nvarchar](50) NULL,
PRIMARY KEY CLUSTERED 
(
	[log_id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
ALTER TABLE [dbo].[task_list] ADD  CONSTRAINT [DF__task_list__statu__0CBAE877]  DEFAULT ('PENDING') FOR [status]
GO
ALTER TABLE [dbo].[task_list] ADD  CONSTRAINT [DF__task_list__prior__0EA330E9]  DEFAULT ((0)) FOR [sequence]
GO
ALTER TABLE [dbo].[task_list] ADD  CONSTRAINT [DF__task_list__creat__1273C1CD]  DEFAULT (getdate()) FOR [created_at]
GO
ALTER TABLE [dbo].[task_list] ADD  CONSTRAINT [DF__task_list__updat__1367E606]  DEFAULT (getdate()) FOR [updated_at]
GO
```