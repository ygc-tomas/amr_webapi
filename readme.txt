# Web API プロジェクト

## 概要
このAPIはwebAPIタスク実行機能を提供します。

## 必要な環境
　- PHP >= 8.3（最新の8.4以上は追加モジュールをインストールできないため）
        - 追加ファイルを
　- MySQL >= 5.7
　- Apache
　
## ファイル構造
webAPI/
├── php/                   # PHP本体
│   ├── php.ini            # 設定済みのphp.iniファイル
│   └── ext/               # 必要な拡張モジュール
│       ├── php_sqlsrv.dll
│       └── php_pdo_sqlsrv.dll
├── /htdocs
│		├──/api
│		│    └──  callback   (GET callbackステータス取得用)
│		├──/api/v3           (レスポンスサンプル格納フォルダ)
│		│    ├── vehicle         (GET AMRステータス取得用)
│		│    └── missionWorks    (POST タスク発行用)
│		└── executeTask.php （webAPIタスク実行）
└──  readme.txt　（本手順書）

## セットアップ手順
1.  php8.3をダウンロードし、phpの設定ファイルと拡張モジュールを追加する。

2.　/htdocsのフォルダ配下すべてを、Apacheのhtdocsフォルダ配下へ展開する。

3.　下記のDB作成コマンドをSSMSで実行し、テーブルを作成する。（テストデータなし）

4.    ローカル8080でサーバーを起動します。


## DB作成コマンド
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
begin
EXEC [amr_task_db].[dbo].[sp_fulltext_database] @action = 'enable'
end
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
/****** Object:  Table [dbo].[task_list]    Script Date: 1/30/2025 3:24:25 PM ******/
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
 CONSTRAINT [PK__task_list__0AD2A005] PRIMARY KEY CLUSTERED 
(
	[id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY],
 CONSTRAINT [UQ__task_list__0BC6C43E] UNIQUE NONCLUSTERED 
(
	[mission_id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
/****** Object:  Table [dbo].[task_logs]    Script Date: 1/30/2025 3:24:25 PM ******/
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
PRIMARY KEY CLUSTERED 
(
	[log_id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
SET ANSI_PADDING ON
GO
/****** Object:  Index [idx_task_list_status_priority]    Script Date: 1/30/2025 3:24:25 PM ******/
CREATE NONCLUSTERED INDEX [idx_task_list_status_priority] ON [dbo].[task_list]
(
	[status] ASC,
	[sequence] ASC,
	[created_at] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, SORT_IN_TEMPDB = OFF, DROP_EXISTING = OFF, ONLINE = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
GO
SET ANSI_PADDING ON
GO
/****** Object:  Index [idx_mission_id]    Script Date: 1/30/2025 3:24:25 PM ******/
CREATE NONCLUSTERED INDEX [idx_mission_id] ON [dbo].[task_logs]
(
	[mission_id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, SORT_IN_TEMPDB = OFF, DROP_EXISTING = OFF, ONLINE = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
GO
SET ANSI_PADDING ON
GO
/****** Object:  Index [idx_status]    Script Date: 1/30/2025 3:24:25 PM ******/
CREATE NONCLUSTERED INDEX [idx_status] ON [dbo].[task_logs]
(
	[status] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, SORT_IN_TEMPDB = OFF, DROP_EXISTING = OFF, ONLINE = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
GO
ALTER TABLE [dbo].[task_list] ADD  CONSTRAINT [DF__task_list__statu__0CBAE877]  DEFAULT ('PENDING') FOR [status]
GO
ALTER TABLE [dbo].[task_list] ADD  CONSTRAINT [DF__task_list__prior__0EA330E9]  DEFAULT ((0)) FOR [sequence]
GO
ALTER TABLE [dbo].[task_list] ADD  CONSTRAINT [DF__task_list__creat__1273C1CD]  DEFAULT (getdate()) FOR [created_at]
GO
ALTER TABLE [dbo].[task_list] ADD  CONSTRAINT [DF__task_list__updat__1367E606]  DEFAULT (getdate()) FOR [updated_at]
GO
USE [master]
GO
ALTER DATABASE [amr_task_db] SET  READ_WRITE 
GO


AMRが一台
AME一台につき一つのタスクのみをこなす
レスポンスステータスは毎度更新され、一つの配列のみ持つ

前回提出したソースコードにてコールバックレスポンスの箇所は仮実装で提出した
（コールバック箇所の仮実装：一旦格納先URLを直打ちし、レスポンスファイルのステータスを読み取りに行く（ミッションIDで検索できない））

今回は格納先URLをDBに登録し、ミッションIDで検索し、その格納先URLのレスポンスファイルのStatusを読み込みに行く（ミッションIDで検索できない）
よってレスポンスファイルの中身が一つのミッション、一つのステータスを保持するといった状況のみ利用可能
（AMR側からレスポンスステータス受信時に、ファイル内のJson配列が追加される場合は利用不可、
　既存ミッションIDのJson配列のステータスに更新がかかる場合は利用可能）

ミッションIDを取得後、AMR情報を参照。
レスポンスデータのworkStatusが1のAMRIDを抽出。
AMRIDごとにcall_back_urlに登録されたURLに対し、該当するAMR別のレスポンスファイルディレクトリを付与してアクセス。
例:http://127.0.0.1:8080/api/callback → http://127.0.0.1:8080/api/callback/amr1
各AMRごとのレスポンスデータを取得可能となる。