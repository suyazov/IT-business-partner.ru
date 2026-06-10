import { z } from "zod";
import { createRouter, publicQuery } from "../middleware";
import { getDb } from "../queries/connection";
import { posts } from "../../db/schema";
import { eq, desc } from "drizzle-orm";

export const blogRouter = createRouter({
  list: publicQuery.query(async () => {
    const db = getDb();
    return db.select().from(posts).where(eq(posts.published, true)).orderBy(desc(posts.createdAt));
  }),

  getBySlug: publicQuery
    .input(z.object({ slug: z.string() }))
    .query(async ({ input }) => {
      const db = getDb();
      const results = await db
        .select()
        .from(posts)
        .where(eq(posts.slug, input.slug))
        .limit(1);
      return results[0] ?? null;
    }),

  create: publicQuery
    .input(
      z.object({
        slug: z.string().min(1).max(255),
        title: z.string().min(1).max(255),
        excerpt: z.string().min(1).max(500),
        content: z.string().min(1),
        metaTitle: z.string().max(255).optional(),
        metaDescription: z.string().max(500).optional(),
        keywords: z.string().max(500).optional(),
        coverImage: z.string().max(500).optional(),
      })
    )
    .mutation(async ({ input }) => {
      const db = getDb();
      const result = await db.insert(posts).values({
        slug: input.slug,
        title: input.title,
        excerpt: input.excerpt,
        content: input.content,
        metaTitle: input.metaTitle ?? input.title,
        metaDescription: input.metaDescription ?? input.excerpt,
        keywords: input.keywords,
        coverImage: input.coverImage,
        published: true,
      });
      return { id: Number(result[0].insertId) };
    }),
});
