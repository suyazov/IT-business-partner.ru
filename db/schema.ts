import {
  mysqlTable,
  serial,
  varchar,
  text,
  timestamp,
  boolean,
} from "drizzle-orm/mysql-core";

// Blog posts table
export const posts = mysqlTable("posts", {
  id: serial("id").primaryKey(),
  slug: varchar("slug", { length: 255 }).notNull().unique(),
  title: varchar("title", { length: 255 }).notNull(),
  excerpt: varchar("excerpt", { length: 500 }).notNull(),
  content: text("content").notNull(),
  metaTitle: varchar("meta_title", { length: 255 }),
  metaDescription: varchar("meta_description", { length: 500 }),
  keywords: varchar("keywords", { length: 500 }),
  coverImage: varchar("cover_image", { length: 500 }),
  published: boolean("published").notNull().default(true),
  createdAt: timestamp("created_at").notNull().defaultNow(),
  updatedAt: timestamp("updated_at").notNull().defaultNow().onUpdateNow(),
});
