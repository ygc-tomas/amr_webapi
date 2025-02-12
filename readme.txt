# Web API Project

## Overview
This API provides web API task execution functionality.

## Required Environment
- PHP >= 8.3 (Versions 8.4 and above are not supported due to module installation limitations)
- MySQL >= 5.7
- Apache

## File Structure
```
webAPI/
├── /htdocs
│   └── executeTask.php (Executes web API tasks) https://github.com/ygc-tomas/amr_webapi.git.
└── readme.txt (This instruction manual)
```

## Setup Procedure
1. Download PHP 8.3 and add the necessary PHP configuration files and extension modules.
2. Execute git clone "https://github.com/ygc-tomas/amr_webapi.git" at /htdocs
3. Run the following database creation command in SSMS to create tables (without test data).
4. Start the server locally on port 8080.

## Database Creation Command
```sql
USE [master]
GO
/****** Object:  Database [amr_task_db]    Script Date: 1/30/2025 3:24:25 PM ******/
CREATE DATABASE [amr_task_db] ON  PRIMARY
( NAME = N'amr_task_db', FILENAME = N'c:\Program Files (x86)\Microsoft SQL Server\MSSQL.1\MSSQL\DATA\amr_task_db.mdf' , SIZE = 2048KB , MAXSIZE = UNLIMITED, FILEGROWTH = 1024KB )
 LOG ON
( NAME = N'amr_task_db_log', FILENAME = N'c:\Program Files (x86)\Microsoft SQL Server\MSSQL.1\MSSQL\DATA\amr_task_db_log.ldf' , SIZE = 1024KB , MAXSIZE = 2048GB , FILEGROWTH = 10%)
GO
EXEC dbo.sp_dbcmptlevel @dbname=N'amr_task_db', @new_cmptlevel=90
GO
IF (1 = FULLTEXTSERVICEPROPERTY('IsFullTextInstalled'))
BEGIN
EXEC [amr_task_db].[dbo].[sp_fulltext_database] @action = 'enable'
END
GO
ALTER DATABASE [amr_task_db] SET ANSI_NULL_DEFAULT OFF
GO
ALTER DATABASE [amr_task_db] SET ANSI_NULLS OFF
GO
ALTER DATABASE [amr_task_db] SET ANSI_PADDING OFF
GO
ALTER DATABASE [amr_task_db] SET ANSI_WARNINGS OFF
GO
ALTER DATABASE [amr_task_db] SET ARITHABORT OFF
GO
ALTER DATABASE [amr_task_db] SET AUTO_CLOSE OFF
GO
ALTER DATABASE [amr_task_db] SET AUTO_SHRINK OFF
GO
ALTER DATABASE [amr_task_db] SET AUTO_UPDATE_STATISTICS ON
GO
ALTER DATABASE [amr_task_db] SET CURSOR_CLOSE_ON_COMMIT OFF
GO
ALTER DATABASE [amr_task_db] SET CURSOR_DEFAULT  GLOBAL
GO
ALTER DATABASE [amr_task_db] SET CONCAT_NULL_YIELDS_NULL OFF
GO
ALTER DATABASE [amr_task_db] SET NUMERIC_ROUNDABORT OFF
GO
ALTER DATABASE [amr_task_db] SET QUOTED_IDENTIFIER OFF
GO
ALTER DATABASE [amr_task_db] SET RECURSIVE_TRIGGERS OFF
GO
ALTER DATABASE [amr_task_db] SET  DISABLE_BROKER
GO
ALTER DATABASE [amr_task_db] SET AUTO_UPDATE_STATISTICS_ASYNC OFF
GO
ALTER DATABASE [amr_task_db] SET DATE_CORRELATION_OPTIMIZATION OFF
GO
ALTER DATABASE [amr_task_db] SET TRUSTWORTHY OFF
GO
ALTER DATABASE [amr_task_db] SET ALLOW_SNAPSHOT_ISOLATION OFF
GO
ALTER DATABASE [amr_task_db] SET PARAMETERIZATION SIMPLE
GO
ALTER DATABASE [amr_task_db] SET READ_COMMITTED_SNAPSHOT OFF
GO
ALTER DATABASE [amr_task_db] SET RECOVERY SIMPLE
GO
ALTER DATABASE [amr_task_db] SET  MULTI_USER
GO
ALTER DATABASE [amr_task_db] SET PAGE_VERIFY CHECKSUM  
GO
ALTER DATABASE [amr_task_db] SET DB_CHAINING OFF
GO
USE [amr_task_db]
GO

/****** Object:  Table [dbo].[task_list] ******/
CREATE TABLE [dbo].[task_list](
    [id] [int] IDENTITY(1,1) NOT NULL,
    [mission_id] [varchar](36) NOT NULL,
      NULL,
      NOT NULL,
    [sequence] [int] NULL,
    [created_at] [datetime] NULL,
    [updated_at] [datetime] NULL,
    [started_at] [datetime] NULL,
    [end_at] [datetime] NULL,
    [completed_at] [datetime] NULL,
    [error_code] [varchar](50) NULL,
    [error_message] [text] NULL,
    CONSTRAINT [PK__task_list__0AD2A005] PRIMARY KEY CLUSTERED ([id] ASC),
    CONSTRAINT [UQ__task_list__0BC6C43E] UNIQUE NONCLUSTERED ([mission_id] ASC)
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO

/****** Object:  Table [dbo].[task_logs] ******/
CREATE TABLE [dbo].[task_logs](
    [log_id] [int] IDENTITY(1,1) NOT NULL,
    [mission_id] [nvarchar](50) NOT NULL,
      NULL,
      NULL,
      NOT NULL,
    [allocation_status] [nvarchar](20) NULL,
    [sequence] [int] NULL,
    [details] [nvarchar](max) NULL,
    [error_code] [int] NULL,
    [message] [nvarchar](max) NULL,
    [start_time] [datetime] NULL,
    [end_time] [datetime] NULL,
    PRIMARY KEY CLUSTERED ([log_id] ASC)
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO

/****** Indexes ******/
CREATE NONCLUSTERED INDEX [idx_task_list_status_priority] ON [dbo].[task_list] ([status] ASC, [sequence] ASC, [created_at] ASC)
GO
CREATE NONCLUSTERED INDEX [idx_mission_id] ON [dbo].[task_logs] ([mission_id] ASC)
GO
CREATE NONCLUSTERED INDEX [idx_status] ON [dbo].[task_logs] ([status] ASC)
GO

/****** Default Constraints ******/
ALTER TABLE [dbo].[task_list] ADD CONSTRAINT [DF__task_list__statu__0CBAE877] DEFAULT ('PENDING') FOR [status]
GO
ALTER TABLE [dbo].[task_list] ADD CONSTRAINT [DF__task_list__prior__0EA330E9] DEFAULT ((0)) FOR [sequence]
GO
ALTER TABLE [dbo].[task_list] ADD CONSTRAINT [DF__task_list__creat__1273C1CD] DEFAULT (getdate()) FOR [created_at]
GO
ALTER TABLE [dbo].[task_list] ADD CONSTRAINT [DF__task_list__updat__1367E606] DEFAULT (getdate()) FOR [updated_at]
GO

USE [master]
GO
ALTER DATABASE [amr_task_db] SET  READ_WRITE
GO
```
